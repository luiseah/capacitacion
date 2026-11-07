<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return Sanctum::actingAs(User::factory()->create(['name' => 'Boss', 'lastname' => 'Admin', 'email' => 'boss@example.com'])->assignRole(RoleName::Admin->value));
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_users_without_permission_get_403(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Requester->value));

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_admin_lists_users_with_roles(): void
    {
        $this->admin();
        User::factory()->create(['name' => 'Ana', 'lastname' => 'Torres', 'phone' => '+34 612 345 678'])->assignRole(RoleName::Requester->value);

        $this->getJson('/api/v1/users?with[]=roles')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ana')
            ->assertJsonPath('data.0.lastname', 'Torres')
            ->assertJsonPath('data.0.full_name', 'Ana Torres')
            ->assertJsonPath('data.0.phone', '+34 612 345 678')
            ->assertJsonPath('data.0.roles', ['requester'])
            ->assertJsonPath('data.1.name', 'Boss')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonPath('total', 2);
    }

    public function test_the_relations_to_include_are_validated_against_the_enum(): void
    {
        $this->admin();

        $this->getJson('/api/v1/users?with[]=roles')->assertOk();
        $this->getJson('/api/v1/users?with[]=password')->assertUnprocessable()->assertJsonValidationErrors('with.0');
        $this->getJson('/api/v1/users?with[]=roles&with[]=roles')->assertUnprocessable()->assertJsonValidationErrors('with.0');
        $this->getJson('/api/v1/users?with=roles')->assertUnprocessable()->assertJsonValidationErrors('with');
    }

    public function test_filters_by_search_and_role(): void
    {
        $this->admin();
        User::factory()->create(['name' => 'Ana', 'lastname' => 'Torres', 'email' => 'ana@example.com'])->assignRole(RoleName::Requester->value);
        User::factory()->create(['name' => 'Luis', 'lastname' => 'Perez', 'email' => 'luis@example.com']);

        $this->getJson('/api/v1/users?search=ana')->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Ana');
        $this->getJson('/api/v1/users?role=admin')->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Boss');
    }

    public function test_sorts_users(): void
    {
        $this->admin();
        User::factory()->create(['name' => 'Ana', 'lastname' => 'Torres']);

        $this->getJson('/api/v1/users?sort[name]=desc')->assertOk()->assertJsonPath('data.0.name', 'Boss');
        $this->getJson('/api/v1/users?sort[name]=asc')->assertOk()->assertJsonPath('data.0.name', 'Ana');
        $this->getJson('/api/v1/users?sort[name]=sideways')->assertUnprocessable();
    }

    public function test_rejects_an_unknown_role(): void
    {
        $this->admin();

        $this->getJson('/api/v1/users?role=nope')->assertUnprocessable();
    }

    public function test_users_page_is_only_for_users_with_permission(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(RoleName::Requester->value))
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin->value))
            ->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page->component('users/index'));
    }

    public function test_show_and_edit_pages_receive_only_the_user_id(): void
    {
        $target = User::factory()->create();
        $admin = User::factory()->create()->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)->get(route('users.show', $target))
            ->assertInertia(fn (Assert $page) => $page->component('users/show')->where('userId', $target->id));
        $this->actingAs($admin)->get(route('users.edit', $target))
            ->assertInertia(fn (Assert $page) => $page->component('users/form')->where('userId', $target->id));

        $this->actingAs(User::factory()->create()->assignRole(RoleName::Requester->value))
            ->get(route('users.edit', $target))
            ->assertForbidden();
    }

    public function test_shows_a_user_with_abilities(): void
    {
        $this->admin();
        $target = User::factory()->create()->assignRole(RoleName::Requester->value);

        $this->getJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $target->email)
            ->assertJsonPath('data.roles', ['requester'])
            ->assertJsonPath('data.abilities', ['update' => true, 'delete' => true]);
    }

    public function test_show_update_and_delete_require_permission(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Requester->value));
        $target = User::factory()->create();

        $this->getJson("/api/v1/users/{$target->id}")->assertForbidden();
        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'X', 'lastname' => 'Y', 'email' => 'x@example.com', 'roles' => []])->assertForbidden();
        $this->deleteJson("/api/v1/users/{$target->id}")->assertForbidden();
    }

    public function test_updates_name_lastname_phone_email_and_roles(): void
    {
        $this->admin();
        $target = User::factory()->create()->assignRole(RoleName::Requester->value);

        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'New', 'lastname' => 'Person', 'phone' => '+57 300 1234567', 'email' => 'new@example.com', 'roles' => ['admin']])
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.lastname', 'Person')
            ->assertJsonPath('data.phone', '+57 300 1234567')
            ->assertJsonPath('data.roles', ['admin']);

        $target->refresh();
        $this->assertSame('new@example.com', $target->email);
        $this->assertNull($target->email_verified_at);
        $this->assertTrue($target->hasRole('admin'));
        $this->assertFalse($target->hasRole('requester'));
    }

    public function test_keeps_verification_when_the_email_does_not_change(): void
    {
        $this->admin();
        $target = User::factory()->create();

        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'Renamed', 'lastname' => 'Same', 'email' => $target->email, 'roles' => []])->assertOk();

        $this->assertNotNull($target->refresh()->email_verified_at);
    }

    public function test_update_validates_input(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->putJson("/api/v1/users/{$target->id}", ['name' => '', 'email' => $admin->email, 'phone' => 'abc', 'roles' => ['nope']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'lastname', 'email', 'phone', 'roles.0']);
    }

    public function test_phone_is_optional_and_can_be_cleared(): void
    {
        $this->admin();
        $target = User::factory()->create(['phone' => '+34 612 345 678']);

        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'A', 'lastname' => 'B', 'email' => $target->email, 'phone' => '', 'roles' => []])->assertOk();

        $this->assertNull($target->refresh()->phone);
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->admin();

        $this->putJson("/api/v1/users/{$admin->id}", ['name' => $admin->name, 'lastname' => $admin->lastname, 'email' => $admin->email, 'roles' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');
    }

    public function test_deletes_a_user(): void
    {
        $this->admin();
        $target = User::factory()->create();

        $this->deleteJson("/api/v1/users/{$target->id}")->assertOk();

        $this->assertModelMissing($target);
    }

    public function test_cannot_delete_yourself(): void
    {
        $admin = $this->admin();

        $this->deleteJson("/api/v1/users/{$admin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot delete your own account here.');
        $this->assertModelExists($admin);
    }

    public function test_cannot_delete_a_user_with_requests(): void
    {
        $this->admin();
        $target = User::factory()->create();
        ServiceRequest::factory()->create(['requester_id' => $target->id]);

        $this->deleteJson("/api/v1/users/{$target->id}")->assertStatus(409);
        $this->assertModelExists($target);
    }
}
