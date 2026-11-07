<?php

namespace Tests\Feature;

use Database\Seeders\InstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\AcademicMobility\Core\Models\Institution;
use Tests\TestCase;

class InstitutionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_cali_colombian_and_international_institutions_with_one_local(): void
    {
        $this->seed(InstitutionSeeder::class);

        // 20 from Cali (the local one among them), 10 from the rest of Colombia, 10 abroad.
        $this->assertSame(40, Institution::query()->count());
        $this->assertSame(30, Institution::query()->where('country', 'CO')->count());
        $this->assertSame(10, Institution::query()->where('country', '!=', 'CO')->count());
        $this->assertSame(['Universidad Santiago de Cali'], Institution::query()->where('is_local', true)->pluck('name')->all());
    }

    public function test_running_it_twice_adds_nothing(): void
    {
        $this->seed(InstitutionSeeder::class);
        $this->seed(InstitutionSeeder::class);

        $this->assertSame(40, Institution::query()->count());
        $this->assertSame(1, Institution::query()->where('is_local', true)->count());
    }
}
