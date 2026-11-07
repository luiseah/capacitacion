<?php

namespace Module\Agreements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Module\AcademicMobility\Http\Resources\InstitutionResource;
use Module\Agreements\Enums\AgreementActivity;
use Module\Agreements\Models\Agreement;

/**
 * @mixin Agreement
 */
class AgreementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'institution' => InstitutionResource::make($this->whenLoaded('institution')),
            'type' => $this->type->value,
            'parties' => $this->parties->value,
            // The other institutions of a multilateral agreement (the main one is `institution`).
            'institutions' => InstitutionResource::collection($this->whenLoaded('institutions')),
            'framework_id' => $this->framework_id,
            'framework' => $this->whenLoaded('framework', fn () => $this->framework === null ? null : [
                'id' => $this->framework->id,
                'name' => $this->framework->name,
                'reference' => $this->framework->reference,
            ]),
            'name' => $this->name,
            'reference' => $this->reference,
            'purpose' => $this->purpose,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            // Where it stands today: as recorded, except that an agreement in force past its end date is expired.
            'status' => $this->statusOn()->value,
            'recorded_status' => $this->status->value,
            'signed_on' => $this->signed_on?->toDateString(),
            'renewal' => $this->renewal->value,
            // National when every institution is in the local country: it follows from them.
            'scope' => $this->scope($this->localCountry($request))->value,
            'has_document' => $this->hasDocument(),
            'document_name' => $this->document_name,
            'activities' => $this->activities->map(fn (AgreementActivity $activity) => $activity->value)->values()->all(),
            'other_activity' => $this->other_activity,
            'notes' => $this->notes,
        ];
    }

    /**
     * The country of the local institution, looked up once per request.
     */
    private function localCountry(Request $request): ?string
    {
        if (! $request->attributes->has('academic-mobility.local-country')) {
            $request->attributes->set(
                'academic-mobility.local-country',
                config('academic-mobility.models.institution')::local()?->country,
            );
        }

        return $request->attributes->get('academic-mobility.local-country');
    }
}
