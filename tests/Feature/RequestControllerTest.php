<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\RoleName;
use App\Models\PurchaseRequest;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequestControllerTest extends TestCase
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

    private function purchasePayload(array $overrides = []): array
    {
        return [
            'title' => 'Laptops',
            'description' => 'For the new team',
            'priority' => 'normal',
            'action' => 'draft',
            'metadata' => [
                'item_description' => '10 laptops',
                'quantity' => 10,
                'estimated_amount' => 15000,
                'currency' => 'USD',
                'justification' => 'Team growth',
            ],
            ...$overrides,
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('requests.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permissions_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('requests.create'))
            ->assertForbidden();
    }

    public function test_create_redirects_to_the_first_available_type(): void
    {
        $this->actingAs($this->requester())
            ->get(route('requests.create'))
            ->assertRedirect(route('requests.form', 'book_reservation'));

        RequestType::where('key', 'book_reservation')->update(['is_active' => false]);

        $this->actingAs($this->requester())
            ->get(route('requests.create'))
            ->assertRedirect(route('requests.form', 'purchase'));
    }

    public function test_form_page_receives_the_fields_of_the_type_in_the_url(): void
    {
        $this->actingAs($this->requester())
            ->get(route('requests.form', 'work_permit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/form')
                ->has('types', 4)
                ->where('type.key', 'work_permit')
                ->has('fields', 5));
    }

    public function test_unknown_or_inactive_types_return_404(): void
    {
        $user = $this->requester();

        $this->actingAs($user)->get(route('requests.form', 'nonsense'))->assertNotFound();

        RequestType::where('key', 'purchase')->update(['is_active' => false]);

        $this->actingAs($user)->get(route('requests.form', 'purchase'))->assertNotFound();
        $this->actingAs($user)->post(route('requests.store', 'purchase'), $this->purchasePayload())->assertNotFound();
    }

    public function test_store_persists_a_draft_in_the_type_model(): void
    {
        $user = $this->requester();

        $response = $this->actingAs($user)->post(route('requests.store', 'purchase'), $this->purchasePayload());

        $request = PurchaseRequest::firstOrFail();
        $response->assertRedirect(route('requests.show', $request));

        $this->assertSame(RequestStatus::Draft, $request->status);
        $this->assertSame($user->id, $request->requester_id);
        $this->assertSame('10 laptops', $request->metadata['item_description']);
    }

    public function test_store_with_submit_action_submits_the_request(): void
    {
        $this->actingAs($this->requester())
            ->post(route('requests.store', 'purchase'), $this->purchasePayload(['action' => 'submit']))
            ->assertSessionHasNoErrors();

        $request = PurchaseRequest::firstOrFail();
        $this->assertSame(RequestStatus::Submitted, $request->status);
        $this->assertNotNull($request->submitted_at);
    }

    public function test_metadata_errors_are_reported_per_field(): void
    {
        $payload = $this->purchasePayload();
        $payload['metadata']['currency'] = 'XXX';
        unset($payload['metadata']['quantity']);

        $this->actingAs($this->requester())
            ->post(route('requests.store', 'purchase'), $payload)
            ->assertSessionHasErrors(['metadata.currency', 'metadata.quantity']);

        $this->assertSame(0, ServiceRequest::count());
    }

    public function test_requesters_only_see_their_own_requests(): void
    {
        $owner = $this->requester();
        $other = $this->requester();

        $this->actingAs($owner)->post(route('requests.store', 'purchase'), $this->purchasePayload());
        $request = PurchaseRequest::firstOrFail();

        $this->actingAs($other)->get(route('requests.show', $request))->assertForbidden();
        $this->actingAs($other)->get(route('requests.index'))
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));

        $this->actingAs($owner)->get(route('requests.show', $request))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('requests/show')->where('request.title', 'Laptops'));
    }

    public function test_admins_can_see_every_request(): void
    {
        $this->actingAs($this->requester())->post(route('requests.store', 'purchase'), $this->purchasePayload());
        $admin = User::factory()->create()->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)->get(route('requests.index'))
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));
    }

    public function test_superadmins_bypass_every_permission_check(): void
    {
        $owner = $this->requester();
        $this->actingAs($owner)->post(route('requests.store', 'purchase'), $this->purchasePayload());
        $request = PurchaseRequest::firstOrFail();

        $superadmin = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($superadmin)->get(route('requests.show', $request))->assertOk();
        $this->actingAs($superadmin)->get(route('requests.index'))
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));
    }
}
