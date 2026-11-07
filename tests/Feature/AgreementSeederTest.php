<?php

namespace Tests\Feature;

use Database\Seeders\AgreementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\AcademicMobility\Core\Enums\AgreementActivity;
use Module\AcademicMobility\Core\Enums\AgreementParties;
use Module\AcademicMobility\Core\Enums\AgreementRenewal;
use Module\AcademicMobility\Core\Enums\AgreementStatus;
use Module\AcademicMobility\Core\Enums\AgreementType;
use Module\AcademicMobility\Core\Models\Agreement;
use Module\AcademicMobility\Core\Models\Institution;
use Tests\TestCase;

class AgreementSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_partner_has_one_to_three_agreements_with_the_local_institution_never_the_local_one(): void
    {
        $this->seed(AgreementSeeder::class);

        $local = Institution::local();
        $partners = Institution::query()->partners()->withCount('agreements')->get();

        $this->assertSame(0, $local->agreements()->count());
        $this->assertSame(39, $partners->count());
        $this->assertSame([1, 2, 3], $partners->pluck('agreements_count')->unique()->sort()->values()->all());
        $this->assertSame($partners->sum('agreements_count'), Agreement::count());
        $this->assertSame(Agreement::count(), Agreement::query()->distinct()->count('reference'));
    }

    public function test_the_agreements_cover_every_activity_in_any_combination_and_every_validity(): void
    {
        $this->seed(AgreementSeeder::class);

        foreach (AgreementActivity::cases() as $activity) {
            $this->assertGreaterThan(0, Agreement::query()->withActivity($activity)->count(), $activity->value);
        }

        // Several activities in one agreement.
        $this->assertTrue(Agreement::all()->contains(fn (Agreement $agreement) => $agreement->activities->count() >= 3));
        $this->assertTrue(Agreement::all()->contains(fn (Agreement $agreement) => $agreement->activities->count() === 1));

        foreach (AgreementStatus::cases() as $status) {
            $this->assertGreaterThan(0, Agreement::query()->withStatus($status)->count(), $status->value);
        }
        $this->assertGreaterThan(0, Agreement::query()->whereNull('ends_on')->count());

        // "Other" always says what it is.
        Agreement::query()->withActivity(AgreementActivity::Other)->get()
            ->each(fn (Agreement $agreement) => $this->assertNotEmpty($agreement->other_activity));
    }

    public function test_running_the_seeder_twice_adds_nothing(): void
    {
        $this->seed(AgreementSeeder::class);
        $count = Agreement::count();

        $this->seed(AgreementSeeder::class);

        $this->assertSame($count, Agreement::count());
    }

    public function test_there_are_framework_and_specific_agreements_and_specifics_develop_their_framework(): void
    {
        $this->seed(AgreementSeeder::class);

        $this->assertGreaterThan(0, Agreement::query()->frameworks()->count());
        $this->assertGreaterThan(0, Agreement::query()->specifics()->count());

        // A specific agreement that names a framework one names a framework of its own institution.
        $linked = Agreement::query()->specifics()->whereNotNull('framework_id')->with('framework')->get();
        $this->assertGreaterThan(0, $linked->count());
        $linked->each(function (Agreement $agreement) {
            $this->assertSame(AgreementType::Framework, $agreement->framework->type);
            $this->assertSame($agreement->institution_id, $agreement->framework->institution_id);
        });

        // Frameworks never depend on another agreement.
        $this->assertSame(0, Agreement::query()->frameworks()->whereNotNull('framework_id')->count());
    }

    public function test_there_are_bilateral_and_multilateral_agreements_and_the_latter_bind_several_institutions(): void
    {
        $this->seed(AgreementSeeder::class);

        $this->assertGreaterThan(0, Agreement::query()->ofParties(AgreementParties::Bilateral)->count());

        $multilateral = Agreement::query()->ofParties(AgreementParties::Multilateral)->with(['institution', 'institutions'])->get();
        $this->assertGreaterThan(0, $multilateral->count());

        $multilateral->each(function (Agreement $agreement) {
            // The main institution and two more, none of them the local one.
            $this->assertCount(2, $agreement->institutions);
            $this->assertCount(3, $agreement->partners()->unique('id'));
            $this->assertFalse($agreement->partners()->contains(fn ($institution) => $institution->is_local));
        });

        // Bilateral ones have no other institutions.
        $this->assertSame(0, Agreement::query()->ofParties(AgreementParties::Bilateral)->whereHas('institutions')->count());

        // An institution that only joins a multilateral agreement still has it among its agreements.
        $joined = $multilateral->first()->institutions->first();
        $this->assertTrue(Agreement::query()->forInstitution($joined->id)->ofParties(AgreementParties::Multilateral)->exists());
    }

    public function test_agreements_come_with_their_purpose_signing_date_renewal_and_scope(): void
    {
        $this->seed(AgreementSeeder::class);

        $agreements = Agreement::query()->with(['institution', 'institutions'])->get();

        // What each one is for, and how it renews: every kind appears.
        $this->assertSame(0, $agreements->filter(fn (Agreement $agreement) => blank($agreement->purpose))->count());
        $this->assertEqualsCanonicalizing(
            array_map(fn (AgreementRenewal $renewal) => $renewal->value, AgreementRenewal::cases()),
            $agreements->pluck('renewal')->map->value->unique()->values()->all(),
        );

        // Signed before it starts to be in force, except the ones that are not signed yet.
        $agreements->each(function (Agreement $agreement) {
            if (in_array($agreement->status, [AgreementStatus::Draft, AgreementStatus::InProcess], true)) {
                $this->assertNull($agreement->signed_on);
            } else {
                $this->assertTrue($agreement->signed_on->lt($agreement->starts_on), $agreement->name);
            }
        });

        // The scope follows from the institutions: Colombian ones are national, the rest international.
        $local = Institution::local()->country;
        $this->assertGreaterThan(0, Agreement::query()->byScope(\Module\AcademicMobility\Core\Enums\MobilityScope::National)->count());
        $this->assertGreaterThan(0, Agreement::query()->byScope(\Module\AcademicMobility\Core\Enums\MobilityScope::International)->count());
        $agreements->each(fn (Agreement $agreement) => $this->assertSame(
            $agreement->partners()->every(fn ($institution) => $institution->country === $local) ? 'national' : 'international',
            $agreement->scope($local)->value,
        ));
        $this->assertSame($agreements->count(), Agreement::query()->byScope(\Module\AcademicMobility\Core\Enums\MobilityScope::National)->count() + Agreement::query()->byScope(\Module\AcademicMobility\Core\Enums\MobilityScope::International)->count());
    }
}
