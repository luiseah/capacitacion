<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_search_matches_name_lastname_email_or_phone(): void
    {
        User::factory()->create(['name' => 'Ana', 'lastname' => 'Torres', 'email' => 'a1@example.com', 'phone' => '+34 612 345 678']);
        User::factory()->create(['name' => 'Luis', 'lastname' => 'Perez', 'email' => 'luis@corp.test', 'phone' => null]);

        $names = fn (string $text) => User::query()->bySearch($text)->pluck('name')->all();

        $this->assertSame(['Ana'], $names('torres'));
        $this->assertSame(['Luis'], $names('corp.test'));
        $this->assertSame(['Ana'], $names('ana torres'));
        $this->assertSame(['Ana'], $names('612345'));
        $this->assertSame(['Ana'], $names('612 345'));
        $this->assertCount(2, User::query()->bySearch('  ')->get());
        $this->assertCount(2, User::query()->bySearch(null)->get());
    }

    public function test_search_ignores_accents_case_and_matches_every_word(): void
    {
        User::factory()->create(['name' => 'José', 'lastname' => 'Núñez', 'email' => 'a1@example.com']);
        User::factory()->create(['name' => 'Jose', 'lastname' => 'Perez', 'email' => 'a2@example.com']);

        $this->assertCount(2, User::query()->bySearch('JOSE')->get());
        $this->assertSame(['Núñez'], User::query()->bySearch('nunez')->pluck('lastname')->all());
        $this->assertSame(['Núñez'], User::query()->bySearch('núñez jose')->pluck('lastname')->all());
        $this->assertCount(0, User::query()->bySearch('jose zzz')->get());
    }

    public function test_search_matches_the_id(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);
        User::factory()->create(['name' => 'Luis']);

        $this->assertTrue(User::query()->bySearch((string) $user->id)->pluck('id')->contains($user->id));
    }

    public function test_search_treats_wildcards_literally(): void
    {
        User::factory()->create(['name' => 'Ana']);

        $this->assertCount(0, User::query()->bySearch('%')->get());
    }

    public function test_with_role_and_verified_filters(): void
    {
        User::factory()->create(['name' => 'Admin'])->assignRole(RoleName::Admin->value);
        User::factory()->unverified()->create(['name' => 'Req'])->assignRole(RoleName::Requester->value);

        $this->assertSame(['Admin'], User::query()->byRole(RoleName::Admin)->pluck('name')->all());
        $this->assertCount(2, User::query()->byRole(null)->get());
        $this->assertSame(['Admin'], User::query()->verified()->pluck('name')->all());
    }

    public function test_alphabetical_orders_by_name_then_lastname(): void
    {
        User::factory()->create(['name' => 'Zoe', 'lastname' => 'A']);
        User::factory()->create(['name' => 'Abe', 'lastname' => 'Z']);
        User::factory()->create(['name' => 'Abe', 'lastname' => 'B']);

        $this->assertSame(['Abe B', 'Abe Z', 'Zoe A'], User::query()->alphabetical()->get()->pluck('full_name')->all());
    }

    public function test_full_name_joins_name_and_lastname(): void
    {
        $this->assertSame('Ana Torres Gil', User::factory()->make(['name' => 'Ana', 'lastname' => 'Torres Gil'])->full_name);
        $this->assertSame('Ana', User::factory()->make(['name' => 'Ana', 'lastname' => ''])->full_name);
    }

    public function test_apply_filters_reads_search_and_role_from_the_request(): void
    {
        User::factory()->create(['name' => 'Ana', 'lastname' => 'Admin', 'email' => 'a1@example.com'])->assignRole(RoleName::Admin->value);
        User::factory()->create(['name' => 'Ana', 'lastname' => 'Req', 'email' => 'a2@example.com'])->assignRole(RoleName::Requester->value);
        User::factory()->create(['name' => 'Luis', 'lastname' => 'Perez', 'email' => 'a3@example.com'])->assignRole(RoleName::Admin->value);

        $names = fn (array $query) => User::query()
            ->applyFilters(Request::create('/', 'GET', $query))
            ->alphabetical()
            ->get()
            ->pluck('full_name')
            ->all();

        $this->assertSame(['Ana Admin', 'Ana Req', 'Luis Perez'], $names([]));
        $this->assertSame(['Ana Admin', 'Ana Req'], $names(['search' => 'ana']));
        $this->assertSame(['Ana Admin', 'Luis Perez'], $names(['role' => 'admin']));
        $this->assertSame(['Ana Admin'], $names(['search' => 'ana', 'role' => 'admin']));
        $this->assertSame(['Ana Admin', 'Ana Req', 'Luis Perez'], $names(['role' => 'unknown']));
    }

    public function test_sort_defaults_to_name_and_honors_the_request(): void
    {
        User::factory()->create(['name' => 'bruno', 'lastname' => 'Alfa', 'email' => 'z@example.com']);
        User::factory()->create(['name' => 'Abe', 'lastname' => 'Zeta', 'email' => 'y@example.com']);
        User::factory()->create(['name' => 'Cara', 'lastname' => 'mu', 'email' => 'x@example.com']);

        $names = fn (array $query) => User::query()
            ->sort(Request::create('/', 'GET', $query))
            ->pluck('name')
            ->all();

        // Case-insensitive, ascending by default.
        $this->assertSame(['Abe', 'bruno', 'Cara'], $names([]));
        $this->assertSame(['Cara', 'bruno', 'Abe'], $names(['sort' => ['name' => 'desc']]));
        $this->assertSame(['Cara', 'Abe', 'bruno'], $names(['sort' => ['email' => 'asc']]));
        $this->assertSame(['bruno', 'Cara', 'Abe'], $names(['sort' => ['lastname' => 'asc']]));
        // Unknown columns, bad directions and non-array input fall back safely.
        $this->assertCount(3, $names(['sort' => ['password' => 'desc']]));
        $this->assertSame(['Abe', 'bruno', 'Cara'], $names(['sort' => 'name']));
        $this->assertSame(['Abe', 'bruno', 'Cara'], $names(['sort' => ['name' => 'sideways']]));
    }

    public function test_the_user_seeder_creates_spanish_users_with_roles(): void
    {
        $this->seed(\Database\Seeders\UserSeeder::class);

        $this->assertSame(50, User::query()->count());
        $this->assertGreaterThan(0, User::query()->byRole(RoleName::Admin)->count());
        $this->assertGreaterThan(0, User::query()->byRole(RoleName::Requester)->count());
        $this->assertSame(5, User::query()->whereNull('email_verified_at')->count());
        $this->assertSame(0, User::query()->where('email', 'not like', '%@example.com')->count());
        $this->assertSame(0, User::query()->where('lastname', '')->count());
        $this->assertSame(0, User::query()->where('phone', 'not like', '+34 %')->count());
    }
}
