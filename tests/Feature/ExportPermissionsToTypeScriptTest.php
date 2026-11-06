<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Tests\TestCase;

class ExportPermissionsToTypeScriptTest extends TestCase
{
    private string $path = 'storage/framework/testing/permissions.ts';

    protected function tearDown(): void
    {
        @unlink(base_path($this->path));

        parent::tearDown();
    }

    public function test_it_exports_every_role_and_permission(): void
    {
        $this->artisan('permissions:export-ts', ['--path' => $this->path])->assertSuccessful();

        $contents = file_get_contents(base_path($this->path));

        foreach (RoleName::cases() as $role) {
            $this->assertStringContainsString("{$role->name}: '{$role->value}'", $contents);
        }

        foreach (PermissionName::cases() as $permission) {
            $this->assertStringContainsString("'{$permission->value}'", $contents);
        }

        // Permissions are grouped by resource with camelCase keys.
        $this->assertStringContainsString("export const Requests = {", $contents);
        $this->assertStringContainsString("destroy: 'requests.destroy',", $contents);
        $this->assertStringNotContainsString('rolePermissions', $contents);
    }

    public function test_check_fails_when_the_file_is_missing_or_outdated(): void
    {
        $this->artisan('permissions:export-ts', ['--path' => $this->path, '--check' => true])->assertFailed();

        $this->artisan('permissions:export-ts', ['--path' => $this->path])->assertSuccessful();
        $this->artisan('permissions:export-ts', ['--path' => $this->path, '--check' => true])->assertSuccessful();

        file_put_contents(base_path($this->path), '// stale');
        $this->artisan('permissions:export-ts', ['--path' => $this->path, '--check' => true])->assertFailed();
    }

    public function test_the_committed_typescript_file_is_in_sync_with_the_enums(): void
    {
        $this->artisan('permissions:export-ts', ['--check' => true])->assertSuccessful();
    }
}
