<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\PurchaseRequest;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, RequestTypeSeeder::class]);
    }

    private function requester(): User
    {
        return Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Requester->value));
    }

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Laptops',
            'priority' => 'normal',
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

    public function test_unauthenticated_requests_get_401_json(): void
    {
        $this->getJson('/api/v1/requests')->assertUnauthorized();
        $this->getJson('/api/v1/request-types')->assertUnauthorized();
    }

    public function test_lists_active_request_types_with_their_fields(): void
    {
        $this->requester();

        $this->getJson('/api/v1/request-types')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Successful')
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.key', 'book_reservation');

        $this->getJson('/api/v1/request-types/work_permit')
            ->assertOk()
            ->assertJsonPath('data.key', 'work_permit')
            ->assertJsonCount(5, 'data.fields');

        $this->getJson('/api/v1/request-types/nonsense')->assertNotFound();

        RequestType::where('key', 'purchase')->update(['is_active' => false]);
        $this->getJson('/api/v1/request-types/purchase')->assertNotFound();
    }

    public function test_store_creates_a_request_and_returns_201(): void
    {
        $user = $this->requester();

        $this->postJson('/api/v1/requests/purchase', $this->payload(['action' => 'submit']))
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Request created')
            ->assertJsonPath('data.title', 'Laptops')
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.type_key', 'purchase');

        $request = PurchaseRequest::firstOrFail();
        $this->assertSame($user->id, $request->requester_id);
        $this->assertSame(RequestStatus::Submitted, $request->status);
    }

    public function test_store_defaults_to_draft_without_action(): void
    {
        $this->requester();

        $this->postJson('/api/v1/requests/purchase', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_store_returns_422_with_metadata_errors(): void
    {
        $this->requester();

        $payload = $this->payload();
        $payload['metadata']['currency'] = 'XXX';
        unset($payload['metadata']['quantity']);

        $this->postJson('/api/v1/requests/purchase', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.currency', 'metadata.quantity']);
    }

    public function test_users_without_permission_cannot_create(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/requests/purchase', $this->payload())->assertForbidden();
    }

    public function test_requesters_only_see_their_own_requests(): void
    {
        $owner = $this->requester();
        $this->postJson('/api/v1/requests/purchase', $this->payload());
        $uuid = PurchaseRequest::firstOrFail()->uuid;

        $this->getJson("/api/v1/requests/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonCount(7, 'data.details');

        $this->requester();
        $this->getJson("/api/v1/requests/{$uuid}")->assertForbidden();
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs(User::factory()->create()->givePermissionTo(PermissionName::IndexRequests->value, PermissionName::ShowRequests->value));
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonCount(1, 'data');
        $this->assertNotNull($owner);
    }

    public function test_update_and_delete_a_draft_through_the_api(): void
    {
        $this->requester();
        $this->postJson('/api/v1/requests/purchase', $this->payload());
        $request = PurchaseRequest::firstOrFail();

        $this->putJson("/api/v1/requests/{$request->uuid}", $this->payload(['title' => 'Updated', 'action' => 'submit']))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Request updated')
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.status', 'submitted');

        // Once submitted the owner can no longer edit or delete it.
        $this->putJson("/api/v1/requests/{$request->uuid}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/v1/requests/{$request->uuid}")->assertForbidden();
    }

    public function test_update_returns_422_and_delete_returns_success_for_a_draft(): void
    {
        $this->requester();
        $this->postJson('/api/v1/requests/purchase', $this->payload());
        $request = PurchaseRequest::firstOrFail();

        $invalid = $this->payload();
        unset($invalid['metadata']['quantity']);

        $this->putJson("/api/v1/requests/{$request->uuid}", $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata.quantity']);

        $this->deleteJson("/api/v1/requests/{$request->uuid}")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Request deleted');

        $this->assertSoftDeleted('requests', ['id' => $request->id]);
    }

    public function test_the_spa_can_call_the_api_with_its_session_cookie(): void
    {
        $user = User::factory()->create()->assignRole(RoleName::Requester->value);

        $this->actingAs($user)
            ->withHeaders(['Referer' => 'http://localhost'])
            ->getJson('/api/v1/requests')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_each_type_validates_cross_field_rules_with_its_own_form_request(): void
    {
        $this->requester();

        $this->postJson('/api/v1/requests/work_permit', [
            'title' => 'Day off',
            'priority' => 'normal',
            'metadata' => [
                'permit_type' => 'vacation',
                'start_at' => '2026-12-01 08:00:00',
                'end_at' => '2026-12-01 07:00:00',
                'reason' => 'Rest',
            ],
        ])->assertJsonValidationErrors(['metadata.permit_type', 'metadata.end_at']);

        $this->postJson('/api/v1/requests/grade_change', [
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
        ])->assertJsonValidationErrors(['metadata.requested_grade']);

        $this->postJson('/api/v1/requests/book_reservation', [
            'title' => 'Book',
            'priority' => 'normal',
            'metadata' => [
                'book_title' => 'Dune',
                'copies' => 1,
                'pickup_date' => now()->addDays(5)->toDateString(),
                'return_date' => now()->addDays(2)->toDateString(),
            ],
        ])->assertJsonValidationErrors(['metadata.return_date']);

        $this->assertSame(0, ServiceRequest::count());
    }

    public function test_unknown_metadata_keys_are_not_stored(): void
    {
        $this->requester();

        $this->postJson('/api/v1/requests/work_permit', [
            'title' => 'Day off',
            'priority' => 'normal',
            'metadata' => [
                'permit_type' => 'medical',
                'start_at' => '2026-12-01 08:00:00',
                'end_at' => '2026-12-01 17:00:00',
                'reason' => 'Doctor appointment',
                'injected' => 'ignored',
            ],
        ])->assertCreated();

        $this->assertArrayNotHasKey('injected', ServiceRequest::firstOrFail()->metadata);
    }

    public function test_inactive_types_are_not_found_when_creating(): void
    {
        $this->requester();
        RequestType::where('key', 'purchase')->update(['is_active' => false]);

        $this->postJson('/api/v1/requests/purchase', $this->payload())->assertNotFound();
    }

    public function test_users_with_the_update_permission_can_edit_a_submitted_request(): void
    {
        $this->requester();
        $this->postJson('/api/v1/requests/purchase', $this->payload(['action' => 'submit']));
        $uuid = PurchaseRequest::firstOrFail()->uuid;

        // The owner can no longer change a submitted request.
        $this->getJson("/api/v1/requests/{$uuid}")
            ->assertJsonPath('data.abilities.update', false)
            ->assertJsonPath('data.abilities.delete', false);

        Sanctum::actingAs(User::factory()->create()->givePermissionTo(PermissionName::ShowRequests->value, PermissionName::UpdateRequests->value));

        $this->getJson("/api/v1/requests/{$uuid}")->assertJsonPath('data.abilities.update', true);
        $this->putJson("/api/v1/requests/{$uuid}", $this->payload(['title' => 'Edited by admin']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Edited by admin')
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_types_expose_the_priorities_and_the_list_is_open_to_everyone(): void
    {
        $this->requester();

        $this->getJson('/api/v1/request-types')->assertJsonPath('priorities', ['low', 'normal', 'high', 'urgent']);

        // Without permissions a user can still list (their own, so none) but not create.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/requests/purchase', $this->payload())->assertForbidden();
    }
}
