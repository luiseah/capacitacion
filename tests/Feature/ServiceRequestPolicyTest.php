<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RequestTypeKey;
use App\Enums\RoleName;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, RequestTypeSeeder::class]);
    }

    private function userWith(PermissionName ...$permissions): User
    {
        return User::factory()->create()->givePermissionTo(array_map(fn ($p) => $p->value, $permissions));
    }

    private function request(): \Database\Factories\ServiceRequestFactory
    {
        return ServiceRequest::factory()->ofType(RequestTypeKey::Purchase);
    }

    public function test_seeder_creates_exactly_the_enum_roles_and_permissions(): void
    {
        Role::create(['name' => 'legacy']);
        Permission::create(['name' => 'requests.manage']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(array_column(RoleName::cases(), 'value'), Role::pluck('name')->all());
        $this->assertEqualsCanonicalizing(array_column(PermissionName::cases(), 'value'), Permission::pluck('name')->all());
    }

    public function test_roles_get_their_permissions(): void
    {
        $requester = User::factory()->create()->assignRole(RoleName::Requester->value);
        $admin = User::factory()->create()->assignRole(RoleName::Admin->value);

        $this->assertSame(['requests.create'], $requester->getAllPermissions()->pluck('name')->all());

        foreach (PermissionName::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
        }
    }

    public function test_owners_see_their_own_requests_and_edit_or_delete_them_while_drafts(): void
    {
        $owner = User::factory()->create();
        $draft = $this->request()->forRequester($owner)->create();

        $this->assertTrue($owner->can('viewAny', ServiceRequest::class));
        $this->assertTrue($owner->can('view', $draft));
        $this->assertTrue($owner->can('update', $draft));
        $this->assertTrue($owner->can('delete', $draft));
    }

    public function test_owners_cannot_change_their_request_once_it_is_submitted(): void
    {
        $owner = User::factory()->create();
        $submitted = $this->request()->forRequester($owner)->submitted()->create();

        $this->assertTrue($owner->can('view', $submitted));
        $this->assertFalse($owner->can('update', $submitted));
        $this->assertFalse($owner->can('delete', $submitted));
    }

    public function test_other_users_without_permissions_cannot_touch_the_request(): void
    {
        $draft = $this->request()->create();
        $other = User::factory()->create();

        $this->assertFalse($other->can('view', $draft));
        $this->assertFalse($other->can('update', $draft));
        $this->assertFalse($other->can('delete', $draft));
    }

    public function test_each_permission_extends_one_action_to_every_request(): void
    {
        $submitted = $this->request()->submitted()->create();

        $viewer = $this->userWith(PermissionName::ShowRequests);
        $this->assertTrue($viewer->can('view', $submitted));
        $this->assertFalse($viewer->can('update', $submitted));
        $this->assertFalse($viewer->can('delete', $submitted));

        $editor = $this->userWith(PermissionName::UpdateRequests);
        $this->assertTrue($editor->can('update', $submitted));
        $this->assertFalse($editor->can('delete', $submitted));
        $this->assertFalse($editor->can('view', $submitted));

        $remover = $this->userWith(PermissionName::DestroyRequests);
        $this->assertTrue($remover->can('delete', $submitted));
        $this->assertFalse($remover->can('update', $submitted));
    }

    public function test_only_the_create_permission_allows_creating(): void
    {
        $this->assertFalse(User::factory()->create()->can('create', ServiceRequest::class));
        $this->assertTrue($this->userWith(PermissionName::CreateRequests)->can('create', ServiceRequest::class));
    }

    public function test_the_index_permission_widens_the_list_to_every_request(): void
    {
        $owner = User::factory()->create();
        $this->request()->forRequester($owner)->create(['title' => 'Mine']);
        $this->request()->create(['title' => 'Theirs']);

        $this->assertSame(['Mine'], ServiceRequest::query()->visibleTo($owner)->pluck('title')->all());

        $lister = $this->userWith(PermissionName::IndexRequests);
        $this->assertEqualsCanonicalizing(['Mine', 'Theirs'], ServiceRequest::query()->visibleTo($lister)->pluck('title')->all());
    }

    public function test_denials_explain_why(): void
    {
        $owner = User::factory()->create();
        $submitted = $this->request()->forRequester($owner)->submitted()->create();
        $stranger = User::factory()->create();

        $this->assertSame('Only draft requests can be edited.', Gate::forUser($owner)->inspect('update', $submitted)->message());
        $this->assertSame('Only draft requests can be deleted.', Gate::forUser($owner)->inspect('delete', $submitted)->message());
        $this->assertSame('You are not allowed to view this request.', Gate::forUser($stranger)->inspect('view', $submitted)->message());
        $this->assertSame('You are not allowed to create requests.', Gate::forUser($stranger)->inspect('create', ServiceRequest::class)->message());
        $this->assertTrue(Gate::forUser($owner)->inspect('viewAny', ServiceRequest::class)->allowed());
    }

    public function test_admins_pass_every_check_without_having_permissions_assigned(): void
    {
        // A bare admin role: no permission is attached to it.
        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions([]);
        $admin = User::factory()->create()->assignRole($role);
        $submitted = $this->request()->submitted()->create();

        $this->assertCount(0, $role->permissions);

        foreach (PermissionName::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
        }

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue($admin->can($ability, $submitted), $ability);
        }

        $this->assertTrue($admin->can('create', ServiceRequest::class));
    }
}
