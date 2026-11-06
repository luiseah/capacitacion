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

    public function test_each_type_validates_cross_field_rules_with_its_own_form_request(): void
    {
        $user = $this->requester();

        $this->actingAs($user)->post(route('requests.store', 'work_permit'), [
            'title' => 'Day off',
            'priority' => 'normal',
            'metadata' => [
                'permit_type' => 'vacation',
                'start_at' => '2026-12-01 08:00:00',
                'end_at' => '2026-12-01 07:00:00',
                'reason' => 'Rest',
            ],
        ])->assertSessionHasErrors(['metadata.permit_type', 'metadata.end_at']);

        $this->actingAs($user)->post(route('requests.store', 'grade_change'), [
            'title' => 'Fix grade',
            'priority' => 'normal',
            'metadata' => [
                'student_name' => 'Ana',
                'course' => 'Math',
                'academic_period' => '2026-1',
                'current_grade' => 3,
                'requested_grade' => 3,
                'reason' => 'Calculation error',
            ],
        ])->assertSessionHasErrors(['metadata.requested_grade']);

        $this->actingAs($user)->post(route('requests.store', 'book_reservation'), [
            'title' => 'Book',
            'priority' => 'normal',
            'metadata' => [
                'book_title' => 'Dune',
                'copies' => 1,
                'pickup_date' => now()->addDays(5)->toDateString(),
                'return_date' => now()->addDays(2)->toDateString(),
            ],
        ])->assertSessionHasErrors(['metadata.return_date']);

        $this->assertSame(0, ServiceRequest::count());
    }

    public function test_valid_work_permit_is_stored_without_unknown_metadata_keys(): void
    {
        $this->actingAs($this->requester())->post(route('requests.store', 'work_permit'), [
            'title' => 'Day off',
            'priority' => 'normal',
            'metadata' => [
                'permit_type' => 'medical',
                'start_at' => '2026-12-01 08:00:00',
                'end_at' => '2026-12-01 17:00:00',
                'reason' => 'Doctor appointment',
                'injected' => 'ignored',
            ],
        ])->assertSessionHasNoErrors();

        $request = ServiceRequest::firstOrFail();
        $this->assertSame('medical', $request->metadata['permit_type']);
        $this->assertArrayNotHasKey('injected', $request->metadata);
    }

    private function draft(User $owner, array $overrides = []): PurchaseRequest
    {
        $this->actingAs($owner)->post(route('requests.store', 'purchase'), $this->purchasePayload($overrides));

        return PurchaseRequest::latest('id')->firstOrFail();
    }

    public function test_owner_can_open_the_edit_form_of_a_draft_with_stored_values(): void
    {
        $owner = $this->requester();
        $request = $this->draft($owner);

        $this->actingAs($owner)->get(route('requests.edit', $request))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/form')
                ->where('type.key', 'purchase')
                ->where('request.title', 'Laptops')
                ->where('request.metadata.currency', 'USD')
                ->has('fields', 7));
    }

    public function test_owner_can_update_a_draft_and_submit_it(): void
    {
        $owner = $this->requester();
        $request = $this->draft($owner);

        $payload = $this->purchasePayload(['title' => 'Better laptops', 'action' => 'submit']);
        $payload['metadata']['quantity'] = 20;

        $this->actingAs($owner)->put(route('requests.update', $request), $payload)
            ->assertRedirect(route('requests.show', $request))
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('Better laptops', $request->title);
        $this->assertSame(20, $request->metadata['quantity']);
        $this->assertSame(RequestStatus::Submitted, $request->status);
    }

    public function test_update_validates_with_the_type_form_request(): void
    {
        $owner = $this->requester();
        $request = $this->draft($owner);

        $payload = $this->purchasePayload();
        $payload['metadata']['currency'] = 'XXX';

        $this->actingAs($owner)->put(route('requests.update', $request), $payload)
            ->assertSessionHasErrors(['metadata.currency']);

        $this->assertSame('USD', $request->fresh()->metadata['currency']);
    }

    public function test_owner_cannot_edit_or_delete_a_submitted_request(): void
    {
        $owner = $this->requester();
        $request = $this->draft($owner, ['action' => 'submit']);

        $this->actingAs($owner)->get(route('requests.edit', $request))->assertForbidden();
        $this->actingAs($owner)->put(route('requests.update', $request), $this->purchasePayload())->assertForbidden();
        $this->actingAs($owner)->delete(route('requests.destroy', $request))->assertForbidden();
    }

    public function test_other_users_cannot_edit_or_delete(): void
    {
        $request = $this->draft($this->requester());
        $other = $this->requester();

        $this->actingAs($other)->get(route('requests.edit', $request))->assertForbidden();
        $this->actingAs($other)->delete(route('requests.destroy', $request))->assertForbidden();
    }

    public function test_managers_can_edit_a_submitted_request(): void
    {
        $request = $this->draft($this->requester(), ['action' => 'submit']);
        $admin = User::factory()->create()->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)->put(route('requests.update', $request), $this->purchasePayload(['title' => 'Edited by admin']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Edited by admin', $request->fresh()->title);
        $this->assertSame(RequestStatus::Submitted, $request->fresh()->status);
    }

    public function test_owner_can_delete_a_draft(): void
    {
        $owner = $this->requester();
        $request = $this->draft($owner);

        $this->actingAs($owner)->delete(route('requests.destroy', $request))
            ->assertRedirect(route('requests.index'));

        $this->assertSoftDeleted('requests', ['id' => $request->id]);
        $this->actingAs($owner)->get(route('requests.show', $request))->assertNotFound();
    }

    public function test_show_exposes_edit_and_delete_abilities(): void
    {
        $owner = $this->requester();
        $draft = $this->draft($owner);
        $submitted = $this->draft($owner, ['action' => 'submit']);

        $this->actingAs($owner)->get(route('requests.show', $draft))
            ->assertInertia(fn (Assert $page) => $page->where('canEdit', true)->where('canDelete', true));

        $this->actingAs($owner)->get(route('requests.show', $submitted))
            ->assertInertia(fn (Assert $page) => $page->where('canEdit', false)->where('canDelete', false));
    }
}
