<?php

namespace Module\AcademicMobility\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Module\AcademicMobility\Authorization\MobilityAbility;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Tests\Support\CreatesInstitutions;
use Module\AcademicMobility\Tests\Support\TestUser;
use Module\AcademicMobility\Tests\TestCase;

class InstitutionApiTest extends TestCase
{
    use CreatesInstitutions;

    private const URL = '/api/academic-mobility/institutions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createInstitutions();
    }

    private function actingWith(MobilityAbility ...$abilities): void
    {
        $granted = array_map(fn (MobilityAbility $a) => $a->value, $abilities);

        foreach (MobilityAbility::cases() as $ability) {
            Gate::define($ability->value, fn ($user) => in_array($ability->value, $granted, true));
        }

        $this->actingAs((new TestUser)->forceFill(['id' => 1]));
    }

    public function test_guests_and_users_without_the_ability_are_rejected(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();

        $this->actingWith();
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, ['name' => 'X', 'country' => 'US'])->assertForbidden();
        $this->putJson(self::URL.'/'.$this->foreign->id, ['name' => 'X', 'country' => 'FR'])->assertForbidden();
        $this->deleteJson(self::URL.'/'.$this->foreign->id)->assertForbidden();
    }

    public function test_lists_with_the_local_one_first_and_searches_by_name(): void
    {
        $this->actingWith(MobilityAbility::ViewAnyInstitutions);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.is_local', true);

        $this->getJson(self::URL.'?search=sorb')->assertJsonPath('total', 1)->assertJsonPath('data.0.country', 'FR');
        // Accents and case do not matter.
        $this->getJson(self::URL.'?search=SORBONNE')->assertJsonPath('total', 1);
        Institution::create(['name' => 'Universidad Católica', 'country' => 'ES']);
        $this->getJson(self::URL.'?search=catolica')->assertJsonPath('total', 1);
    }

    public function test_creates_an_institution_and_upper_cases_the_country(): void
    {
        $this->actingWith(MobilityAbility::CreateInstitutions);

        $this->postJson(self::URL, ['name' => 'MIT', 'country' => 'us'])
            ->assertCreated()
            ->assertJsonPath('data.country', 'US')
            ->assertJsonPath('data.is_local', false);
    }

    public function test_validates_the_payload(): void
    {
        $this->actingWith(MobilityAbility::CreateInstitutions);

        $this->postJson(self::URL, [])->assertJsonValidationErrors(['name', 'country']);
        $this->postJson(self::URL, ['name' => 'X', 'country' => 'USA'])->assertJsonValidationErrors('country');
        $this->postJson(self::URL, ['name' => 'X', 'country' => '12'])->assertJsonValidationErrors('country');
    }

    public function test_making_another_institution_local_demotes_the_previous_one(): void
    {
        $this->actingWith(MobilityAbility::UpdateInstitutions);

        $this->putJson(self::URL.'/'.$this->national->id, ['name' => 'Barcelona', 'country' => 'ES', 'is_local' => true])
            ->assertOk()
            ->assertJsonPath('data.is_local', true);

        $this->assertTrue($this->national->refresh()->is_local);
        $this->assertFalse($this->local->refresh()->is_local);
        $this->assertSame(1, Institution::query()->where('is_local', true)->count());
    }

    public function test_the_local_institution_cannot_be_demoted_directly(): void
    {
        $this->actingWith(MobilityAbility::UpdateInstitutions);

        $this->putJson(self::URL.'/'.$this->local->id, ['name' => 'Local', 'country' => 'ES', 'is_local' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_local');
    }

    public function test_the_database_allows_a_single_local_institution(): void
    {
        $this->expectException(QueryException::class);

        Institution::create(['name' => 'Second local', 'country' => 'ES', 'is_local' => true]);
    }

    public function test_deletes_unused_institutions_only(): void
    {
        $this->actingWith(MobilityAbility::DeleteInstitutions);

        Mobility::create([
            'participant_type' => 'student', 'participant_id' => '1', 'strategy' => 'default',
            'status' => MobilityStatus::Draft, 'origin_institution_id' => $this->local->id,
            'destination_institution_id' => $this->foreign->id,
        ]);

        $this->deleteJson(self::URL.'/'.$this->foreign->id)->assertStatus(409);
        $this->deleteJson(self::URL.'/'.$this->local->id)->assertForbidden();
        $this->deleteJson(self::URL.'/'.$this->national->id)->assertOk();

        $this->assertSoftDeleted($this->national);
    }

    public function test_the_host_can_protect_institutions_its_data_points_to(): void
    {
        $this->actingWith(MobilityAbility::DeleteInstitutions);

        AcademicMobility::preventInstitutionDeletionUsing(
            fn (Institution $institution) => $institution->is($this->national) ? 'It has visitors.' : null,
        );

        $this->deleteJson(self::URL.'/'.$this->national->id)->assertStatus(409)->assertJsonPath('message', 'It has visitors.');
        $this->assertNotSoftDeleted($this->national);

        $this->deleteJson(self::URL.'/'.$this->foreign->id)->assertOk();
    }

    public function test_the_list_filters_by_scope_and_search_through_the_builder(): void
    {
        $this->actingWith(MobilityAbility::ViewAnyInstitutions);

        $names = fn (string $query) => collect($this->getJson(self::URL.'?'.$query)->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Universidad Local', 'Universidad de Barcelona'], $names('scope=national'));
        $this->assertSame(['Sorbonne'], $names('scope=international'));
        $this->assertSame(['Universidad Local', 'Universidad de Barcelona'], $names('search=universidad'));

        $builder = Institution::query();
        $this->assertSame(['Sorbonne'], $builder->byScope(MobilityScope::International)->pluck('name')->all());
        $this->assertSame(['Sorbonne', 'Universidad de Barcelona'], Institution::query()->partners()->localFirst()->pluck('name')->all());
    }

    public function test_an_institution_a_guard_opposes_to_cannot_be_deleted(): void
    {
        $this->actingWith(MobilityAbility::DeleteInstitutions);

        AcademicMobility::preventInstitutionDeletionUsing(
            fn (Institution $institution) => $institution->is($this->national) ? 'Kept by a guard.' : null,
        );

        $this->deleteJson(self::URL.'/'.$this->national->id)->assertStatus(409)->assertJsonPath('message', 'Kept by a guard.');
        $this->assertNotSoftDeleted($this->national);
        $this->deleteJson(self::URL.'/'.$this->foreign->id)->assertOk();
    }
}
