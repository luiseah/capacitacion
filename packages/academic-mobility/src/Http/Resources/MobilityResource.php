<?php

namespace Module\AcademicMobility\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Module\AcademicMobility\Core\Models\Mobility;

/**
 * @mixin Mobility
 */
class MobilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'participant_type' => $this->participant_type,
            'participant_id' => $this->participant_id,
            'strategy' => $this->strategy,
            'direction' => $this->direction?->value,
            'scope' => $this->scope?->value,
            'origin' => InstitutionResource::make($this->whenLoaded('originInstitution')),
            'destination' => InstitutionResource::make($this->whenLoaded('destinationInstitution')),
            'agreement_id' => $this->resource->getAttribute('agreement_id'),
            'requirements' => RequirementResource::collection($this->whenLoaded('requirements')),
            'status' => $this->status->value,
            'is_final' => $this->status->isFinal(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
