<?php

namespace App\Console\Commands;

use App\Support\PermissionsTypeScriptExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:export-ts {--path= : Output file, relative to the project root} {--check : Fail when the file is out of date instead of writing it}')]
#[Description('Export the roles and permissions enums as a TypeScript module')]
class ExportPermissionsToTypeScript extends Command
{
    private const DEFAULT_PATH = 'resources/js/generated/permissions.ts';

    public function handle(PermissionsTypeScriptExporter $exporter): int
    {
        $path = base_path($this->option('path') ?: self::DEFAULT_PATH);
        $contents = $exporter->render();

        if ($this->option('check')) {
            if (! is_file($path) || file_get_contents($path) !== $contents) {
                $this->error('Permissions TypeScript file is out of date. Run `php artisan permissions:export-ts`.');

                return self::FAILURE;
            }

            $this->info('Permissions TypeScript file is up to date.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);

        $this->info('Permissions exported to '.str_replace(base_path().'/', '', $path));

        return self::SUCCESS;
    }
}
