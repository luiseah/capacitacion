<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\RequestTypeKey;
use App\Http\Requests\Requests\ServiceRequestFormRequest;
use App\Models\BookReservationRequest;
use App\Models\GradeChangeRequest;
use App\Models\PurchaseRequest;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkPermitRequest;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RequestTypeSeeder::class);
    }

    public function test_seeder_creates_the_four_request_types(): void
    {
        $this->assertSame(4, RequestType::count());

        foreach (RequestTypeKey::cases() as $key) {
            $this->assertSame($key, RequestType::where('key', $key->value)->firstOrFail()->key);
        }
    }

    public function test_each_key_resolves_a_strategy_bound_to_its_own_model(): void
    {
        $expected = [
            RequestTypeKey::BookReservation->value => BookReservationRequest::class,
            RequestTypeKey::Purchase->value => PurchaseRequest::class,
            RequestTypeKey::GradeChange->value => GradeChangeRequest::class,
            RequestTypeKey::WorkPermit->value => WorkPermitRequest::class,
        ];

        foreach (RequestTypeKey::cases() as $key) {
            $model = $key->strategy()->requestModel();

            $this->assertSame($expected[$key->value], $model);
            $this->assertSame($key, $model::typeKey());
            $this->assertNotEmpty($model::fields());
            $this->assertTrue(is_subclass_of($key->strategy()->formRequest(), ServiceRequestFormRequest::class));
        }
    }

    public function test_concrete_models_are_scoped_to_their_type(): void
    {
        $user = User::factory()->create();

        $purchase = PurchaseRequest::create(['requester_id' => $user->id, 'title' => 'Laptops', 'metadata' => []]);
        WorkPermitRequest::create(['requester_id' => $user->id, 'title' => 'Day off', 'metadata' => []]);

        $this->assertSame(RequestTypeKey::Purchase, $purchase->type->key);
        $this->assertSame(1, PurchaseRequest::count());
        $this->assertSame(1, WorkPermitRequest::count());
        $this->assertSame(0, BookReservationRequest::count());
        $this->assertSame(2, ServiceRequest::count());
    }

    public function test_submit_moves_request_to_submitted(): void
    {
        $request = PurchaseRequest::create([
            'requester_id' => User::factory()->create()->id,
            'title' => 'Laptops',
            'metadata' => [],
        ]);

        $this->assertNotNull($request->uuid);
        $this->assertSame(RequestStatus::Draft, $request->fresh()->status);

        RequestTypeKey::Purchase->strategy()->submit($request);

        $this->assertSame(RequestStatus::Submitted, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->submitted_at);
    }

    public function test_form_request_rules_match_the_fields_shown_in_the_ui(): void
    {
        foreach (RequestTypeKey::cases() as $key) {
            $strategy = $key->strategy();
            $rules = (new ($strategy->formRequest()))->rules();

            $metadataRules = array_filter($rules, fn ($_, $name) => str_starts_with($name, 'metadata.'), ARRAY_FILTER_USE_BOTH);
            $fields = collect($strategy->requestModel()::fields());

            $this->assertEqualsCanonicalizing(
                $fields->map(fn ($field) => "metadata.{$field->key}")->all(),
                array_keys($metadataRules),
                "{$key->value}: fields and rules differ",
            );

            foreach ($fields as $field) {
                $this->assertSame(
                    $field->required,
                    in_array('required', $rules["metadata.{$field->key}"], true),
                    "{$key->value}.{$field->key}: required flag differs from its rules",
                );
            }
        }
    }
}
