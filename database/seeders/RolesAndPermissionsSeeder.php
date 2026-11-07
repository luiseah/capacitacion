<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keeps the roles and permissions table equal to the enums: creates what is
 * missing, syncs each role's permissions and removes what is no longer defined.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        // The registrar keeps the list it loaded: after creating permissions (a fresh database has none) it has to
        // read them again, or the roles cannot find them.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $role) {
            Role::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (PermissionName $p) => $p->value, $role->permissions()));
        }

        Role::query()->whereNotIn('name', array_column(RoleName::cases(), 'value'))->delete();
        Permission::query()->whereNotIn('name', array_column(PermissionName::cases(), 'value'))->delete();
    }
}
