<?php

namespace Module\Agreements\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Module\Agreements\Enums\AgreementActivity;
use Module\Agreements\Enums\AgreementParties;
use Module\Agreements\Enums\AgreementStatus;
use Module\Agreements\Enums\AgreementType;
use Module\Agreements\Exceptions\InvalidAgreement;
use Module\Agreements\Models\Agreement;
use Module\AcademicMobility\Core\Models\Institution;
use Module\Agreements\Tests\Support\CreatesInstitutions;
use Module\Agreements\Tests\TestCase;

class AgreementTest extends TestCase
{
    use CreatesInstitutions;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createInstitutions();
        $this->today = CarbonImmutable::parse('2026-06-15');
        CarbonImmutable::setTestNow($this->today);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agreement(Institution $institution, array $attributes = []): Agreement
    {
        return Agreement::create([
            'institution_id' => $institution->id,
            'name' => 'Cooperation agreement',
            'starts_on' => '2026-01-01',
            'ends_on' => '2027-01-01',
            'activities' => [AgreementActivity::Training],
            ...$attributes,
        ]);
    }

    public function test_an_institution_can_have_many_agreements_with_the_local_one(): void
    {
        $this->agreement($this->foreign, ['name' => 'Framework', 'starts_on' => '2020-01-01', 'ends_on' => '2024-12-31']);
        $this->agreement($this->foreign, ['name' => 'Exchange', 'starts_on' => '2025-01-01']);
        $this->agreement($this->foreign, ['name' => 'Research', 'starts_on' => '2026-03-01']);
        $this->agreement($this->national);

        $this->assertSame(3, Agreement::query()->forInstitution($this->foreign->id)->count());
        $this->assertSame(1, Agreement::query()->forInstitution($this->national->id)->count());
        // The latest first.
        $this->assertSame(['Research', 'Exchange', 'Framework'], Agreement::query()->forInstitution($this->foreign->id)->latestFirst()->pluck('name')->all());
        $this->assertTrue(Agreement::query()->forInstitution($this->foreign->id)->first()->institution->is($this->foreign));
        $this->assertTrue(Schema::hasColumns('academic_agreements', ['institution_id', 'name', 'reference', 'starts_on', 'ends_on', 'activities', 'other_activity']));
    }

    public function test_the_activities_are_a_multiple_choice_of_training_research_extension_administrative_and_other(): void
    {
        $this->assertSame(['training', 'research', 'extension', 'administrative', 'other'], array_column(AgreementActivity::cases(), 'value'));

        $agreement = $this->agreement($this->foreign, [
            'activities' => ['research', AgreementActivity::Administrative, 'other'],
            'other_activity' => 'Joint summer school',
        ]);

        $fresh = Agreement::find($agreement->id);
        $this->assertEquals([AgreementActivity::Research, AgreementActivity::Administrative, AgreementActivity::Other], $fresh->activities->all());
        $this->assertSame('Joint summer school', $fresh->other_activity);
        $this->assertTrue($fresh->covers(AgreementActivity::Research));
        $this->assertTrue($fresh->covers(AgreementActivity::Other));
        $this->assertFalse($fresh->covers(AgreementActivity::Training));
        $this->assertFalse($fresh->covers(AgreementActivity::Extension));

        // All of them at once is allowed.
        $all = $this->agreement($this->foreign, ['activities' => AgreementActivity::cases()]);
        $this->assertCount(5, Agreement::find($all->id)->activities);
    }

    public function test_agreements_are_found_by_what_they_cover(): void
    {
        $research = $this->agreement($this->foreign, ['name' => 'Research', 'activities' => ['research']]);
        $both = $this->agreement($this->foreign, ['name' => 'Both', 'activities' => ['research', 'training']]);
        $mobility = $this->agreement($this->national, ['name' => 'Training', 'activities' => ['training']]);

        $names = fn (AgreementActivity $activity) => Agreement::query()->withActivity($activity)->orderBy('name')->pluck('name')->all();

        $this->assertSame(['Both', 'Research'], $names(AgreementActivity::Research));
        $this->assertSame(['Both', 'Training'], $names(AgreementActivity::Training));
        $this->assertSame([], $names(AgreementActivity::Extension));
        $this->assertSame(['Both', 'Research'], Agreement::query()->forInstitution($this->foreign->id)->withActivity(AgreementActivity::Research)->orderBy('name')->pluck('name')->all());
    }

    public function test_the_status_and_the_dates_decide_if_an_agreement_is_in_force(): void
    {
        $this->assertSame(['draft', 'in_process', 'active', 'expired', 'suspended', 'terminated'], array_column(AgreementStatus::cases(), 'value'));

        $draft = $this->agreement($this->foreign, ['name' => 'Draft', 'status' => 'draft']);
        $inProcess = $this->agreement($this->foreign, ['name' => 'In process', 'status' => 'in_process']);
        $active = $this->agreement($this->foreign, ['name' => 'Active', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $indefinite = $this->agreement($this->foreign, ['name' => 'Indefinite', 'starts_on' => '2019-01-01', 'ends_on' => null]);
        // Recorded as in force, but its end date has passed: expired.
        $lapsed = $this->agreement($this->foreign, ['name' => 'Lapsed', 'starts_on' => '2022-01-01', 'ends_on' => '2026-06-14']);
        $expired = $this->agreement($this->foreign, ['name' => 'Expired', 'status' => 'expired', 'starts_on' => '2020-01-01', 'ends_on' => '2024-01-01']);
        $suspended = $this->agreement($this->foreign, ['name' => 'Suspended', 'status' => 'suspended']);
        $terminated = $this->agreement($this->foreign, ['name' => 'Terminated', 'status' => 'terminated']);
        $lastDay = $this->agreement($this->foreign, ['name' => 'Last day', 'starts_on' => '2025-01-01', 'ends_on' => '2026-06-15']);
        $firstDay = $this->agreement($this->foreign, ['name' => 'First day', 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-30']);
        // Recorded as in force but it has not started: not usable yet.
        $notYet = $this->agreement($this->foreign, ['name' => 'Not yet', 'starts_on' => '2026-09-01', 'ends_on' => '2027-09-01']);

        $this->assertSame(AgreementStatus::Draft, $draft->statusOn());
        $this->assertSame(AgreementStatus::InProcess, $inProcess->statusOn());
        $this->assertSame(AgreementStatus::Active, $active->statusOn());
        $this->assertSame(AgreementStatus::Active, $indefinite->statusOn());
        $this->assertSame(AgreementStatus::Expired, $lapsed->statusOn());
        $this->assertSame(AgreementStatus::Expired, $expired->statusOn());
        $this->assertSame(AgreementStatus::Suspended, $suspended->statusOn());
        $this->assertSame(AgreementStatus::Terminated, $terminated->statusOn());

        // Only an agreement in force, within its dates, is usable; both ends are included.
        $this->assertTrue($active->isInForce());
        $this->assertTrue($lastDay->isInForce());
        $this->assertTrue($firstDay->isInForce());
        $this->assertFalse($lastDay->isInForce($this->today->addDay()));
        foreach ([$draft, $inProcess, $lapsed, $expired, $suspended, $terminated, $notYet] as $unusable) {
            $this->assertFalse($unusable->isInForce(), $unusable->name);
        }
        $this->assertTrue($notYet->isInForce(CarbonImmutable::parse('2026-10-01')));

        $names = fn (AgreementStatus $status, ?CarbonImmutable $date = null) => Agreement::query()->withStatus($status, $date)->orderBy('name')->pluck('name')->all();

        $this->assertSame(['Active', 'First day', 'Indefinite', 'Last day'], $names(AgreementStatus::Active));
        $this->assertSame(['Expired', 'Lapsed'], $names(AgreementStatus::Expired));
        $this->assertSame(['Draft'], $names(AgreementStatus::Draft));
        $this->assertSame(['In process'], $names(AgreementStatus::InProcess));
        $this->assertSame(['Suspended'], $names(AgreementStatus::Suspended));
        $this->assertSame(['Terminated'], $names(AgreementStatus::Terminated));
        $this->assertSame(['Active', 'Indefinite', 'Not yet'], Agreement::query()->inForce(CarbonImmutable::parse('2026-09-01'))->orderBy('name')->pluck('name')->all());
    }

    public function test_the_rules_of_an_agreement_hold_however_it_is_saved(): void
    {
        $this->assertThrows(fn () => $this->agreement($this->local), InvalidAgreement::class);
        $this->assertThrows(fn () => $this->agreement($this->foreign, ['starts_on' => '2026-06-01', 'ends_on' => '2026-05-31']), InvalidAgreement::class);
        $this->assertThrows(fn () => $this->agreement($this->foreign, ['activities' => []]), InvalidAgreement::class);

        // The same day is a one-day agreement.
        $this->assertNotNull($this->agreement($this->foreign, ['starts_on' => '2026-06-01', 'ends_on' => '2026-06-01']));

        // Editing keeps the rules too.
        $agreement = $this->agreement($this->foreign);
        $this->assertThrows(fn () => $agreement->update(['ends_on' => '2025-12-31']), InvalidAgreement::class);
        $this->assertThrows(fn () => $agreement->update(['institution_id' => $this->local->id]), InvalidAgreement::class);
        // Only the valid ones were stored.
        $this->assertSame(2, Agreement::count());
    }

    public function test_an_agreement_is_a_framework_one_or_a_specific_one(): void
    {
        $this->assertSame(['framework', 'specific'], array_column(AgreementType::cases(), 'value'));

        $framework = $this->agreement($this->foreign, ['name' => 'Framework', 'type' => AgreementType::Framework, 'activities' => AgreementActivity::cases(), 'other_activity' => 'Anything']);
        $this->agreement($this->foreign, ['name' => 'Exchange', 'type' => 'specific', 'framework_id' => $framework->id]);
        $this->agreement($this->foreign, ['name' => 'Summer school', 'framework_id' => $framework->id, 'activities' => ['research', 'training']]);
        $this->agreement($this->national, ['name' => 'Standalone', 'type' => 'specific']);

        $this->assertTrue($framework->isFramework());
        $this->assertSame(AgreementType::Framework, Agreement::find($framework->id)->type);
        $this->assertSame(['Framework'], Agreement::query()->frameworks()->pluck('name')->all());
        $this->assertSame(['Exchange', 'Standalone', 'Summer school'], Agreement::query()->specifics()->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Framework'], Agreement::query()->forInstitution($this->foreign->id)->ofType(AgreementType::Framework)->pluck('name')->all());

        // A specific agreement can say which framework it develops, and a framework lists its specifics.
        $this->assertCount(2, $framework->specifics);
        $this->assertTrue(Agreement::where('name', 'Exchange')->first()->framework->is($framework));
        $this->assertNull(Agreement::where('name', 'Standalone')->first()->framework);
    }

    public function test_a_specific_agreement_only_depends_on_a_framework_one_of_its_institution(): void
    {
        $framework = $this->agreement($this->foreign, ['type' => 'framework']);
        $specific = $this->agreement($this->foreign, ['name' => 'Specific']);

        // A framework agreement does not depend on another one.
        $this->assertThrows(fn () => $this->agreement($this->foreign, ['type' => 'framework', 'framework_id' => $framework->id]), InvalidAgreement::class);
        // Not on another institution's framework, nor on a specific agreement.
        $this->assertThrows(fn () => $this->agreement($this->national, ['framework_id' => $framework->id]), InvalidAgreement::class);
        $this->assertThrows(fn () => $this->agreement($this->foreign, ['framework_id' => $specific->id]), InvalidAgreement::class);
        $this->assertThrows(fn () => $specific->update(['framework_id' => 'missing']), InvalidAgreement::class);

        $this->assertNotNull($specific->update(['framework_id' => $framework->id]));
        // Without a type it is a specific agreement.
        $this->assertSame(AgreementType::Specific, $this->agreement($this->foreign)->type);
    }

    public function test_an_agreement_is_bilateral_or_multilateral(): void
    {
        $this->assertSame(['bilateral', 'multilateral'], array_column(AgreementParties::cases(), 'value'));

        $third = Institution::create(['name' => 'Toronto', 'country' => 'CA']);

        // Bilateral by default: the local institution and one partner.
        $bilateral = $this->agreement($this->foreign, ['name' => 'Bilateral']);
        $this->assertSame(AgreementParties::Bilateral, $bilateral->parties);
        $this->assertFalse($bilateral->isMultilateral());
        $this->assertSame([$this->foreign->id], $bilateral->partners()->pluck('id')->all());

        // Multilateral: the main institution and the others of the network.
        $network = $this->agreement($this->foreign, ['name' => 'Network', 'parties' => 'multilateral']);
        $network->syncInstitutions([$this->national->id, $third->id]);

        $this->assertTrue($network->isMultilateral());
        $this->assertEqualsCanonicalizing([$this->foreign->id, $this->national->id, $third->id], $network->partners()->pluck('id')->all());
        $this->assertSame(['Toronto', 'Universidad de Barcelona'], $network->institutions->pluck('name')->all());

        // An institution takes part in its own agreements and in the multilateral ones that include it.
        $this->assertSame(['Bilateral', 'Network'], Agreement::query()->forInstitution($this->foreign->id)->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Network'], Agreement::query()->forInstitution($third->id)->pluck('name')->all());
        $this->assertSame(['Network'], Agreement::query()->forInstitution($this->national->id)->pluck('name')->all());
        $this->assertSame(['Network'], Agreement::query()->ofParties(AgreementParties::Multilateral)->pluck('name')->all());
    }

    public function test_the_institutions_of_an_agreement_follow_its_parties(): void
    {
        $bilateral = $this->agreement($this->foreign, ['name' => 'Bilateral']);
        $network = $this->agreement($this->foreign, ['name' => 'Network', 'parties' => 'multilateral']);

        // A bilateral agreement has no other institutions; a multilateral one needs at least one.
        $this->assertThrows(fn () => $bilateral->syncInstitutions([$this->national->id]), InvalidAgreement::class);
        $this->assertThrows(fn () => $network->syncInstitutions([]), InvalidAgreement::class);
        // Partner institutions only, and not the main one again.
        $this->assertThrows(fn () => $network->syncInstitutions([$this->local->id]), InvalidAgreement::class);
        $this->assertThrows(fn () => $network->syncInstitutions([$this->foreign->id]), InvalidAgreement::class);
        $this->assertThrows(fn () => $network->syncInstitutions(['missing']), InvalidAgreement::class);
        $this->assertCount(0, $network->institutions);

        // The same institution twice counts once, and syncing again replaces the list.
        $network->syncInstitutions([$this->national->id, $this->national->id]);
        $this->assertCount(1, $network->institutions);
        $third = Institution::create(['name' => 'Toronto', 'country' => 'CA']);
        $network->syncInstitutions([$third->id]);
        $this->assertSame(['Toronto'], $network->institutions->pluck('name')->all());

        // Becoming bilateral again drops them.
        $network->update(['parties' => 'bilateral']);
        $network->syncInstitutions([]);
        $this->assertCount(0, $network->institutions()->get());
    }
}
