<?php

namespace Tests\Feature;

use App\Models\AdministrativeStaff;
use App\Models\ExternalParticipant;
use App\Models\Professor;
use App\Models\Student;
use Database\Seeders\ExternalParticipantSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;
use Tests\TestCase;

class ExternalParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_live_in_their_own_table_apart_from_the_internal_people(): void
    {
        $this->seed(ExternalParticipantSeeder::class);

        $this->assertGreaterThan(0, ExternalParticipant::count());
        // No user, student, professor or staff row is created for a visitor.
        $this->assertSame(0, Student::count() + Professor::count() + AdministrativeStaff::count());
        $this->assertFalse(Schema::hasColumn('external_participants', 'user_id'));
    }

    public function test_the_seeder_gives_every_partner_visitors_of_each_kind_and_never_the_local_institution(): void
    {
        $this->seed(ExternalParticipantSeeder::class);

        $local = Institution::local();
        $this->assertSame(0, ExternalParticipant::where('institution_id', $local->id)->count());

        // 10 foreign + 5 Colombian partners, each with 2 students, 1 professor and 1 staff member.
        $this->assertSame(15, ExternalParticipant::distinct()->count('institution_id'));
        $this->assertSame(60, ExternalParticipant::count());
        $this->assertSame(30, ExternalParticipant::where('kind', ParticipantKind::Student)->count());
        $this->assertSame(15, ExternalParticipant::where('kind', ParticipantKind::Professor)->count());
        $this->assertSame(15, ExternalParticipant::where('kind', ParticipantKind::Staff)->count());
        $this->assertSame(10, ExternalParticipant::whereHas('institution', fn ($q) => $q->where('country', '!=', $local->country))->distinct()->count('institution_id'));

        $visitor = ExternalParticipant::first();
        $this->assertNotSame('', $visitor->full_name);
        $this->assertNotNull($visitor->institution->name);
    }

    public function test_running_the_seeder_twice_adds_nothing(): void
    {
        $this->seed(ExternalParticipantSeeder::class);
        $this->seed(ExternalParticipantSeeder::class);

        $this->assertSame(60, ExternalParticipant::count());
    }

    public function test_a_person_is_unique_per_institution_and_kind_by_document(): void
    {
        $visitor = ExternalParticipant::factory()->ofKind(ParticipantKind::Student)->create();

        $this->assertThrows(
            fn () => DB::transaction(fn () => ExternalParticipant::factory()->from($visitor->institution)->ofKind(ParticipantKind::Student)->create(['document_number' => $visitor->document_number])),
            UniqueConstraintViolationException::class,
        );

        // The same document as a professor, or at another institution, is another person.
        $this->assertNotNull(ExternalParticipant::factory()->from($visitor->institution)->ofKind(ParticipantKind::Professor)->create(['document_number' => $visitor->document_number]));
        $this->assertNotNull(ExternalParticipant::factory()->from(Institution::create(['name' => 'Other', 'country' => 'FR']))->ofKind(ParticipantKind::Student)->create(['document_number' => $visitor->document_number]));
    }

    public function test_an_institution_with_visitors_cannot_be_deleted_from_the_database(): void
    {
        $visitor = ExternalParticipant::factory()->create();

        $this->assertThrows(
            fn () => DB::transaction(fn () => DB::table('academic_institutions')->where('id', $visitor->institution_id)->delete()),
            \Illuminate\Database\QueryException::class,
        );
    }

    public function test_the_package_refuses_to_delete_an_institution_with_visitors(): void
    {
        $visitor = ExternalParticipant::factory()->create();

        $this->assertSame('This institution has visitors and cannot be deleted.', \Module\AcademicMobility\Facades\AcademicMobility::institutionDeletionBlockedBy($visitor->institution));
        $this->assertNull(\Module\AcademicMobility\Facades\AcademicMobility::institutionDeletionBlockedBy(Institution::create(['name' => 'Empty', 'country' => 'FR'])));
    }
}
