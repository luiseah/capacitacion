<?php

namespace Module\Agreements\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Module\AcademicMobility\Authorization\MobilityAbility;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\Agreements\Authorization\AgreementAbility;
use Module\Agreements\Models\Agreement;
use Module\Agreements\Tests\Support\ArrayParticipantResolver;
use Module\Agreements\Tests\Support\CreatesInstitutions;
use Module\Agreements\Tests\Support\TestUser;
use Module\Agreements\Tests\TestCase;

class AgreementApiTest extends TestCase
{
    use CreatesInstitutions;

    private const URL = '/api/agreements';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createInstitutions();
        CarbonImmutable::setTestNow('2026-06-15');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function actingWith(MobilityAbility|AgreementAbility ...$abilities): void
    {
        $granted = array_map(fn (MobilityAbility|AgreementAbility $a) => $a->value, $abilities);

        foreach ([...MobilityAbility::cases(), ...AgreementAbility::cases()] as $ability) {
            Gate::define($ability->value, fn ($user) => in_array($ability->value, $granted, true));
        }

        $this->actingAs((new TestUser)->forceFill(['id' => 1]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function agreement(object $institution, string $name, array $attributes = []): Agreement
    {
        return Agreement::create([
            'institution_id' => $institution->id,
            'name' => $name,
            'starts_on' => '2026-01-01',
            'ends_on' => '2027-01-01',
            'activities' => ['training'],
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'institution_id' => $this->foreign->id,
            'type' => 'specific',
            'name' => 'Student exchange',
            'reference' => 'CONV-2026-001',
            'starts_on' => '2026-02-01',
            'ends_on' => '2028-02-01',
            'activities' => ['training', 'research'],
            ...$overrides,
        ];
    }

    public function test_guests_and_users_without_the_ability_are_rejected(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();

        $this->actingWith();
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        $agreement = $this->agreement($this->foreign, 'A');
        $this->putJson(self::URL.'/'.$agreement->id, $this->payload())->assertForbidden();
        $this->deleteJson(self::URL.'/'.$agreement->id)->assertForbidden();
    }

    public function test_lists_agreements_with_their_status_today_and_filters_them(): void
    {
        $framework = $this->agreement($this->foreign, 'Framework', ['type' => 'framework', 'reference' => 'MARCO-1', 'activities' => ['training', 'research', 'extension']]);
        $this->agreement($this->foreign, 'Exchange', ['framework_id' => $framework->id, 'starts_on' => '2026-03-01']);
        $this->agreement($this->foreign, 'Old', ['starts_on' => '2020-01-01', 'ends_on' => '2024-01-01', 'activities' => ['administrative']]);
        $this->agreement($this->national, 'Next year', ['status' => 'in_process', 'starts_on' => '2027-01-01', 'ends_on' => null, 'activities' => ['other'], 'other_activity' => 'Summer school']);

        $this->actingWith(AgreementAbility::ViewAny);
        $names = fn (string $query = '') => collect($this->getJson(self::URL.'?'.$query)->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Next year', 'Exchange', 'Framework', 'Old'], $names());
        $this->assertSame(['Exchange', 'Framework', 'Old'], $names('institution_id='.$this->foreign->id));
        $this->assertSame(['Framework'], $names('type=framework'));
        $this->assertSame(['Exchange', 'Old'], $names('type=specific&institution_id='.$this->foreign->id));
        $this->assertSame(['Exchange', 'Framework'], $names('status=active'));
        $this->assertSame(['Next year'], $names('status=in_process'));
        $this->assertSame(['Old'], $names('status=expired'));
        $this->assertSame([], $names('status=draft'));
        $this->assertSame(['Framework'], $names('activity=extension'));
        $this->assertSame(['Next year'], $names('activity=other'));
        $this->assertSame(['Framework'], $names('search=marco-1'));
        $this->assertSame(['Exchange'], $names('search=EXCH'));

        // Relations come only when asked for (`with`), and only the allowed ones.
        $plain = $this->getJson(self::URL.'?search=exchange')->json('data.0');
        $this->assertArrayNotHasKey('institution', $plain);
        $this->assertArrayNotHasKey('framework', $plain);
        // A relation that is not in the enum is rejected by the request.
        $this->getJson(self::URL.'?search=exchange&with[]=password')->assertUnprocessable()->assertJsonValidationErrors('with.0');

        $first = $this->getJson(self::URL.'?search=exchange&with[]=institution&with[]=framework')->json('data.0');
        $this->assertSame('active', $first['status']);
        $this->assertSame(['training'], $first['activities']);
        $this->assertSame('Framework', $first['framework']['name']);
        $this->assertSame('Sorbonne', $first['institution']['name']);

        // Sorting is part of the same call.
        $this->assertSame(['Next year', 'Exchange', 'Framework', 'Old'], collect($this->getJson(self::URL.'?sort[starts_on]=desc')->json('data'))->pluck('name')->all());
        $this->assertNull($this->getJson(self::URL.'?search=next')->json('data.0.ends_on'));
        $this->assertSame('Summer school', $this->getJson(self::URL.'?search=next')->json('data.0.other_activity'));

        $this->getJson(self::URL.'?type=sideways&status=bad&activity=nope')->assertUnprocessable()->assertJsonValidationErrors(['type', 'status', 'activity']);
    }

    public function test_creates_updates_shows_and_deletes_an_agreement(): void
    {
        $this->actingWith(AgreementAbility::Create, AgreementAbility::Update, AgreementAbility::Delete);

        // A draft unless it says otherwise.
        $id = $this->postJson(self::URL, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'specific')
            ->assertJsonPath('data.activities', ['training', 'research'])
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.institution.name', 'Sorbonne')
            ->json('data.id');

        $this->assertDatabaseHas('academic_agreements', ['id' => $id, 'reference' => 'CONV-2026-001']);

        $this->getJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('data.name', 'Student exchange');

        // A PUT replaces it: what is left out is emptied (no end date, no reference).
        $this->putJson(self::URL.'/'.$id, ['name' => 'Renamed', 'institution_id' => $this->national->id, 'type' => 'framework', 'starts_on' => '2026-02-01', 'activities' => ['other'], 'other_activity' => 'Joint lab'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.type', 'framework')
            ->assertJsonPath('data.ends_on', null)
            ->assertJsonPath('data.reference', null)
            ->assertJsonPath('data.institution.id', $this->national->id)
            ->assertJsonPath('data.other_activity', 'Joint lab');

        $this->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->assertSoftDeleted(Agreement::withTrashed()->find($id));
    }

    public function test_validates_the_agreement(): void
    {
        $this->actingWith(AgreementAbility::Create);

        $this->postJson(self::URL, [])->assertJsonValidationErrors(['institution_id', 'type', 'name', 'starts_on', 'activities']);
        $this->postJson(self::URL, $this->payload(['activities' => []]))->assertJsonValidationErrors('activities');
        $this->postJson(self::URL, $this->payload(['activities' => ['training', 'training']]))->assertJsonValidationErrors('activities.1');
        $this->postJson(self::URL, $this->payload(['activities' => ['teleportation']]))->assertJsonValidationErrors('activities.0');
        // "Other" says what it is.
        $this->postJson(self::URL, $this->payload(['activities' => ['other']]))->assertJsonValidationErrors('other_activity');
        $this->postJson(self::URL, $this->payload(['starts_on' => '2026-05-01', 'ends_on' => '2026-04-30']))->assertJsonValidationErrors('ends_on');
        $this->postJson(self::URL, $this->payload(['type' => 'sideways']))->assertJsonValidationErrors('type');

        // With a partner institution only.
        $this->postJson(self::URL, $this->payload(['institution_id' => $this->local->id]))->assertJsonValidationErrors('institution_id');
        $this->postJson(self::URL, $this->payload(['institution_id' => '01JMISSINGMISSINGMISSING00']))->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseCount('academic_agreements', 0);
    }

    public function test_a_specific_agreement_names_a_framework_of_its_own_institution(): void
    {
        $this->actingWith(AgreementAbility::Create);
        $framework = $this->agreement($this->foreign, 'Framework', ['type' => 'framework']);
        $other = $this->agreement($this->national, 'Other framework', ['type' => 'framework']);
        $specific = $this->agreement($this->foreign, 'Specific');

        $this->postJson(self::URL, $this->payload(['framework_id' => $framework->id]))->assertCreated()->assertJsonPath('data.framework.name', 'Framework');

        $this->postJson(self::URL, $this->payload(['framework_id' => $other->id]))->assertJsonValidationErrors('framework_id');
        $this->postJson(self::URL, $this->payload(['framework_id' => $specific->id]))->assertJsonValidationErrors('framework_id');
        $this->postJson(self::URL, $this->payload(['type' => 'framework', 'framework_id' => $framework->id]))->assertJsonValidationErrors('framework_id');
    }

    public function test_the_creation_form_of_a_mobility_shows_the_agreements_of_the_chosen_institution(): void
    {
        $this->agreement($this->foreign, 'Old', ['starts_on' => '2020-01-01', 'ends_on' => '2024-01-01']);
        $this->agreement($this->foreign, 'Current');
        $this->agreement($this->national, 'Barcelona only');

        $this->actingWith(MobilityAbility::Create);
        $url = '/api/agreements/institution';

        $agreements = $this->getJson($url.'?institution_id='.$this->foreign->id)->assertOk()->assertJsonCount(2, 'data')->json('data');

        $this->assertSame(['Current', 'Old'], array_column($agreements, 'name'));
        $this->assertSame(['active', 'expired'], array_column($agreements, 'status'));
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->getJson($url.'?institution_id='.$this->local->id)->assertJsonCount(0, 'data');

        $this->actingWith(MobilityAbility::View);
        $this->getJson($url.'?institution_id='.$this->foreign->id)->assertForbidden();
    }

    public function test_creates_a_multilateral_agreement_with_its_institutions(): void
    {
        $this->actingWith(AgreementAbility::Create, AgreementAbility::Update, AgreementAbility::ViewAny, MobilityAbility::Create);
        $third = Institution::create(['name' => 'Toronto', 'country' => 'CA']);

        $id = $this->postJson(self::URL, $this->payload(['parties' => 'multilateral', 'institution_ids' => [$this->national->id, $third->id]]))
            ->assertCreated()
            ->assertJsonPath('data.parties', 'multilateral')
            ->assertJsonCount(2, 'data.institutions')
            ->assertJsonPath('data.institutions.0.name', 'Toronto')
            ->json('data.id');

        // It shows for each institution that takes part in it, and is found by what it is.
        foreach ([$this->foreign, $this->national, $third] as $institution) {
            $this->getJson('/api/agreements/institution?institution_id='.$institution->id)
                ->assertJsonPath('data.0.id', $id)
                ->assertJsonPath('data.0.parties', 'multilateral');
        }
        $this->getJson(self::URL.'?institution_id='.$third->id)->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?parties=multilateral')->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?parties=bilateral')->assertJsonCount(0, 'data');

        // A PUT replaces the institutions; becoming bilateral drops them.
        $this->putJson(self::URL.'/'.$id, $this->payload(['parties' => 'multilateral', 'institution_ids' => [$third->id]]))->assertOk()->assertJsonCount(1, 'data.institutions');
        $this->putJson(self::URL.'/'.$id, $this->payload())->assertOk()->assertJsonPath('data.parties', 'bilateral')->assertJsonCount(0, 'data.institutions');
        $this->getJson(self::URL.'?institution_id='.$third->id)->assertJsonCount(0, 'data');
    }

    public function test_validates_the_institutions_of_the_agreement_by_its_parties(): void
    {
        $this->actingWith(AgreementAbility::Create);

        // Multilateral: at least one more institution, partners only, not the main one.
        $this->postJson(self::URL, $this->payload(['parties' => 'multilateral']))->assertJsonValidationErrors('institution_ids');
        $this->postJson(self::URL, $this->payload(['parties' => 'multilateral', 'institution_ids' => []]))->assertJsonValidationErrors('institution_ids');
        $this->postJson(self::URL, $this->payload(['parties' => 'multilateral', 'institution_ids' => [$this->local->id]]))->assertJsonValidationErrors('institution_ids');
        $this->postJson(self::URL, $this->payload(['parties' => 'multilateral', 'institution_ids' => [$this->foreign->id]]))->assertJsonValidationErrors('institution_ids');
        $this->postJson(self::URL, $this->payload(['parties' => 'multilateral', 'institution_ids' => ['01JMISSINGMISSINGMISSING00']]))->assertJsonValidationErrors('institution_ids');
        $this->postJson(self::URL, $this->payload(['parties' => 'multilateral', 'institution_ids' => [$this->national->id, $this->national->id]]))->assertJsonValidationErrors('institution_ids.1');

        // Bilateral: no more institutions. Anything else is not a kind.
        $this->postJson(self::URL, $this->payload(['parties' => 'bilateral', 'institution_ids' => [$this->national->id]]))->assertJsonValidationErrors('institution_ids');
        $this->postJson(self::URL, $this->payload(['parties' => 'sideways']))->assertJsonValidationErrors('parties');

        $this->assertDatabaseCount('academic_agreements', 0);
    }

    public function test_an_institution_in_a_multilateral_agreement_cannot_be_deleted(): void
    {
        $this->actingWith(MobilityAbility::DeleteInstitutions);
        $agreement = $this->agreement($this->foreign, 'Network', ['parties' => 'multilateral']);
        $agreement->syncInstitutions([$this->national->id]);

        $this->deleteJson('/api/academic-mobility/institutions/'.$this->national->id)->assertStatus(409)
            ->assertJsonPath('message', 'This institution has agreements and cannot be deleted.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mobilityPayload(array $overrides = []): array
    {
        return [
            'participant_type' => 'student', 'participant_id' => '1', 'strategy' => 'default',
            'direction' => 'outgoing', 'scope' => 'international', 'institution_id' => $this->foreign->id,
            ...$overrides,
        ];
    }

    public function test_a_mobility_is_made_under_an_agreement_in_force_with_the_other_institution(): void
    {
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([1 => []]));
        $this->actingWith(MobilityAbility::Create, MobilityAbility::View);
        $url = self::URL === '' ? '' : '/api/academic-mobility/mobilities';

        $own = $this->agreement($this->foreign, 'With Sorbonne');
        $created = $this->postJson($url, $this->mobilityPayload(['agreement_id' => $own->id]))
            ->assertCreated()
            ->assertJsonPath('data.agreement_id', $own->id);

        // The request page shows it, and the agreement is optional.
        $this->getJson($url.'/'.$created->json('data.id'))->assertJsonPath('data.agreement_id', $own->id);
        $this->postJson($url, $this->mobilityPayload())->assertCreated()->assertJsonPath('data.agreement_id', null);

        // A multilateral agreement serves every institution that takes part in it.
        $network = $this->agreement($this->national, 'Network', ['parties' => 'multilateral']);
        $network->syncInstitutions([$this->foreign->id]);
        $this->postJson($url, $this->mobilityPayload(['agreement_id' => $network->id]))->assertCreated()->assertJsonPath('data.agreement_id', $network->id);

        // Incoming mobilities use the agreements of the institution they come from.
        $this->postJson($url, $this->mobilityPayload(['direction' => 'incoming', 'agreement_id' => $own->id]))->assertCreated();
    }

    public function test_the_agreement_of_a_mobility_has_to_be_usable(): void
    {
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([1 => []]));
        $this->actingWith(MobilityAbility::Create);
        $url = '/api/academic-mobility/mobilities';

        $elsewhere = $this->agreement($this->national, 'With Barcelona');
        $expired = $this->agreement($this->foreign, 'Expired', ['starts_on' => '2020-01-01', 'ends_on' => '2024-01-01']);
        $upcoming = $this->agreement($this->foreign, 'Upcoming', ['starts_on' => '2026-09-01', 'ends_on' => '2027-09-01']);

        $this->postJson($url, $this->mobilityPayload(['agreement_id' => $elsewhere->id]))->assertUnprocessable()->assertJsonValidationErrors('agreement_id');
        $this->postJson($url, $this->mobilityPayload(['agreement_id' => $expired->id]))->assertUnprocessable()->assertJsonValidationErrors('agreement_id');
        $this->postJson($url, $this->mobilityPayload(['agreement_id' => $upcoming->id]))->assertUnprocessable()->assertJsonValidationErrors('agreement_id');
        $this->postJson($url, $this->mobilityPayload(['agreement_id' => '01JMISSINGMISSINGMISSING00']))->assertUnprocessable()->assertJsonValidationErrors('agreement_id');

        $this->assertDatabaseCount('academic_mobilities', 0);
    }

    public function test_deleting_an_agreement_leaves_the_mobilities_made_under_it(): void
    {
        AcademicMobility::registerParticipantResolver('student', new ArrayParticipantResolver([1 => []]));
        $this->actingWith(MobilityAbility::Create);
        $agreement = $this->agreement($this->foreign, 'With Sorbonne');

        $id = $this->postJson('/api/academic-mobility/mobilities', $this->mobilityPayload(['agreement_id' => $agreement->id]))->assertCreated()->json('data.id');

        $agreement->delete();

        $this->assertNotNull(Mobility::find($id));
        $this->assertNull(Mobility::find($id)->agreement);
    }

    public function test_an_agreement_has_a_status_purpose_signing_date_and_renewal(): void
    {
        $this->actingWith(AgreementAbility::Create, AgreementAbility::Update, AgreementAbility::ViewAny);

        $id = $this->postJson(self::URL, $this->payload([
            'status' => 'in_process',
            'purpose' => 'Exchange of students and joint research projects.',
            'signed_on' => '2026-01-20',
            'renewal' => 'automatic',
        ]))->assertCreated()
            ->assertJsonPath('data.status', 'in_process')
            ->assertJsonPath('data.recorded_status', 'in_process')
            ->assertJsonPath('data.purpose', 'Exchange of students and joint research projects.')
            ->assertJsonPath('data.signed_on', '2026-01-20')
            ->assertJsonPath('data.renewal', 'automatic')
            ->json('data.id');

        // The signing date is not the start of the validity, and the renewal is manual by default.
        $this->putJson(self::URL.'/'.$id, $this->payload(['status' => 'active']))
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.signed_on', null)
            ->assertJsonPath('data.renewal', 'manual')
            ->assertJsonPath('data.purpose', null);

        // An update that does not name the status keeps the one it had.
        $this->putJson(self::URL.'/'.$id, $this->payload(['name' => 'Renamed']))->assertOk()->assertJsonPath('data.status', 'active');

        // One recorded as in force whose end date passed is reported as expired, keeping what was recorded.
        $this->putJson(self::URL.'/'.$id, $this->payload(['status' => 'active', 'starts_on' => '2020-01-01', 'ends_on' => '2024-01-01']))
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.recorded_status', 'active');

        foreach (['draft', 'in_process', 'active', 'expired', 'suspended', 'terminated'] as $status) {
            $this->putJson(self::URL.'/'.$id, $this->payload(['status' => $status, 'ends_on' => '2030-01-01']))->assertOk()->assertJsonPath('data.status', $status);
        }
        $this->getJson(self::URL.'?status=terminated')->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?status=active')->assertJsonCount(0, 'data');

        $this->postJson(self::URL, $this->payload(['status' => 'frozen']))->assertJsonValidationErrors('status');
        $this->postJson(self::URL, $this->payload(['renewal' => 'sometimes']))->assertJsonValidationErrors('renewal');
        $this->postJson(self::URL, $this->payload(['signed_on' => 'yesterday']))->assertJsonValidationErrors('signed_on');
    }

    public function test_the_scope_of_an_agreement_follows_from_its_institutions(): void
    {
        $this->actingWith(AgreementAbility::Create, AgreementAbility::ViewAny);
        $spanish = Institution::create(['name' => 'Complutense', 'country' => 'ES']);

        // The local institution is in Spain: Barcelona is national, Sorbonne is not.
        $national = $this->postJson(self::URL, $this->payload(['institution_id' => $this->national->id]))->assertCreated()->assertJsonPath('data.scope', 'national')->json('data.id');
        $international = $this->postJson(self::URL, $this->payload(['institution_id' => $this->foreign->id]))->assertCreated()->assertJsonPath('data.scope', 'international')->json('data.id');

        // A multilateral agreement is national only when all of its institutions are.
        $allSpanish = $this->postJson(self::URL, $this->payload(['institution_id' => $this->national->id, 'parties' => 'multilateral', 'institution_ids' => [$spanish->id]]))
            ->assertCreated()->assertJsonPath('data.scope', 'national')->json('data.id');
        $mixed = $this->postJson(self::URL, $this->payload(['institution_id' => $this->national->id, 'parties' => 'multilateral', 'institution_ids' => [$this->foreign->id]]))
            ->assertCreated()->assertJsonPath('data.scope', 'international')->json('data.id');

        $ids = fn (string $scope) => collect($this->getJson(self::URL.'?scope='.$scope)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$national, $allSpanish])->sort()->values()->all(), $ids('national'));
        $this->assertSame(collect([$international, $mixed])->sort()->values()->all(), $ids('international'));
        $this->getJson(self::URL.'?scope=galactic')->assertUnprocessable()->assertJsonValidationErrors('scope');
    }

    public function test_the_signed_document_is_uploaded_downloaded_and_removed(): void
    {
        Storage::fake('local');
        $agreement = $this->agreement($this->foreign, 'With a document');
        $pdf = fn (string $name = 'convenio.pdf') => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $url = self::URL.'/'.$agreement->id.'/document';

        // Nothing to download yet, and only who can edit uploads.
        $this->actingWith(AgreementAbility::ViewAny);
        $this->get($url)->assertNotFound();
        $this->post($url, ['document' => $pdf()], ['Accept' => 'application/json'])->assertForbidden();

        $this->actingWith(AgreementAbility::Update, AgreementAbility::ViewAny);
        $this->post($url, ['document' => $pdf()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.has_document', true)
            ->assertJsonPath('data.document_name', 'convenio.pdf');

        Storage::disk('local')->assertExists("agreements/{$agreement->id}.pdf");

        $download = $this->get($url)->assertOk();
        $this->assertSame('application/pdf', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('convenio.pdf', $download->headers->get('Content-Disposition'));

        // Uploading again replaces it; anything that is not a PDF is refused.
        $this->post($url, ['document' => $pdf('nuevo.pdf')], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.document_name', 'nuevo.pdf');
        $this->post($url, ['document' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->post($url, [], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('document');

        $this->deleteJson($url)->assertOk();
        Storage::disk('local')->assertMissing("agreements/{$agreement->id}.pdf");
        $this->assertFalse($agreement->refresh()->hasDocument());
        $this->get($url)->assertNotFound();
    }
}
