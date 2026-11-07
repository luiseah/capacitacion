<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AdministrativeStaff;
use App\Models\Professor;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AdministrativeStaffSeeder;
use Database\Seeders\ProfessorSeeder;
use Database\Seeders\StudentSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstamentoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_seeder_fills_its_table_with_one_user_per_profile(): void
    {
        $this->seed(StudentSeeder::class);
        $this->seed(ProfessorSeeder::class);
        $this->seed(AdministrativeStaffSeeder::class);

        // 30 students, 5 of whom study a second program.
        $this->assertSame(35, Student::count());
        $this->assertSame(30, Student::query()->distinct()->count('user_id'));
        $this->assertSame(12, Professor::count());
        $this->assertSame(8, AdministrativeStaff::count());
        $this->assertSame(50, User::count());

        foreach ([Professor::class, AdministrativeStaff::class] as $model) {
            $this->assertSame($model::count(), $model::query()->distinct()->count('user_id'), "{$model} user_id is unique");
            $this->assertNotNull($model::query()->first()->user->full_name);
        }

        $this->assertTrue(Student::first()->user->hasRole(RoleName::Requester->value));
        $this->assertInstanceOf(StudentStatus::class, Student::first()->status);
        $this->assertInstanceOf(ContractType::class, Professor::first()->contract_type);
        $this->assertNotNull(Professor::first()->hired_at);
    }

    public function test_the_user_reaches_its_profile(): void
    {
        $student = Student::factory()->create();
        $professor = Professor::factory()->create();
        $staff = AdministrativeStaff::factory()->create();

        $this->assertTrue($student->user->students->first()->is($student));
        $this->assertTrue($professor->user->professor->is($professor));
        $this->assertTrue($staff->user->administrativeStaff->is($staff));
        $this->assertNull($student->user->professor);
        $this->assertCount(1, $student->user->students);
    }

    public function test_running_a_seeder_twice_adds_nothing(): void
    {
        $this->seed(StudentSeeder::class);
        $this->seed(StudentSeeder::class);

        $this->assertSame(35, Student::count());
    }

    public function test_a_person_can_study_two_programs_but_not_the_same_one_twice(): void
    {
        $first = Student::factory()->create(['program' => 'Derecho']);

        $second = Student::factory()->for($first->user)->create(['program' => 'Medicina']);
        $this->assertCount(2, $first->user->students);
        $this->assertNotSame($first->code, $second->code);

        // Inside a savepoint, so PostgreSQL keeps the test transaction usable after the violation.
        $this->assertThrows(
            fn () => DB::transaction(fn () => Student::factory()->for($first->user)->create(['program' => 'Derecho'])),
            UniqueConstraintViolationException::class,
        );
    }

    public function test_professors_and_staff_have_one_profile_per_user_and_profiles_follow_their_user(): void
    {
        $professor = Professor::factory()->create();

        $this->assertThrows(
            fn () => DB::transaction(fn () => Professor::factory()->create(['user_id' => $professor->user_id])),
            UniqueConstraintViolationException::class,
        );

        $professor->user->delete();
        $this->assertSame(0, Professor::count());
    }
}
