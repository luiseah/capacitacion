<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\PurchaseRequest;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The web controller only serves pages: they get the identifier they need and
 * load their data through the API (covered by the API tests).
 */
class RequestPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, RequestTypeSeeder::class]);
    }

    private function requester(): User
    {
        return User::factory()->create()->assignRole(RoleName::Requester->value);
    }

    private function draftOf(User $owner): PurchaseRequest
    {
        return PurchaseRequest::create(['requester_id' => $owner->id, 'title' => 'Laptops', 'metadata' => []]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('requests.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permissions_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get(route('requests.create'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('requests.index'))->assertForbidden();
    }

    public function test_index_page_receives_no_props(): void
    {
        $this->actingAs($this->requester())->get(route('requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/index')
                ->missing('requests')
                ->missing('canCreate'));
    }

    public function test_create_redirects_to_the_first_available_type(): void
    {
        $this->actingAs($this->requester())->get(route('requests.create'))
            ->assertRedirect(route('requests.form', 'book_reservation'));

        RequestType::where('key', 'book_reservation')->update(['is_active' => false]);

        $this->actingAs($this->requester())->get(route('requests.create'))
            ->assertRedirect(route('requests.form', 'purchase'));
    }

    public function test_form_page_only_receives_the_type_key(): void
    {
        $this->actingAs($this->requester())->get(route('requests.form', 'work_permit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/form')
                ->where('typeKey', 'work_permit')
                ->missing('fields')
                ->missing('types'));
    }

    public function test_unknown_or_inactive_types_return_404(): void
    {
        $user = $this->requester();

        $this->actingAs($user)->get(route('requests.form', 'nonsense'))->assertNotFound();

        RequestType::where('key', 'purchase')->update(['is_active' => false]);
        $this->actingAs($user)->get(route('requests.form', 'purchase'))->assertNotFound();
    }

    public function test_show_and_edit_pages_only_receive_the_request_uuid(): void
    {
        $owner = $this->requester();
        $request = $this->draftOf($owner);

        $this->actingAs($owner)->get(route('requests.show', $request))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/show')
                ->where('requestUuid', $request->uuid)
                ->missing('request'));

        $this->actingAs($owner)->get(route('requests.edit', $request))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/form')
                ->where('requestUuid', $request->uuid));
    }

    public function test_pages_are_protected_by_the_policy(): void
    {
        $request = $this->draftOf($this->requester());
        $other = $this->requester();

        $this->actingAs($other)->get(route('requests.show', $request))->assertForbidden();
        $this->actingAs($other)->get(route('requests.edit', $request))->assertForbidden();

        $admin = User::factory()->create()->assignRole(RoleName::Admin->value);
        $this->actingAs($admin)->get(route('requests.edit', $request))->assertOk();
    }

    public function test_deleted_requests_are_not_found(): void
    {
        $owner = $this->requester();
        $request = $this->draftOf($owner);
        $request->delete();

        $this->actingAs($owner)->get(route('requests.show', $request->uuid))->assertNotFound();
    }
}
