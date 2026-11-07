<?php

namespace Module\AcademicMobility\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Module\AcademicMobility\Core\Models\MobilityRequirement;
use Module\AcademicMobility\Core\Models\Requirement;

/**
 * A requirement of the catalog or of a mobility. `name` and `description` come in the language of the
 * request; the catalog also exposes the raw texts and the translations to edit them.
 *
 * @mixin Requirement|MobilityRequirement
 */
class RequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->localizedName(),
            'description' => $this->localizedDescription(),
            'is_mandatory' => $this->is_mandatory,
            'position' => $this->position,
            ...($this->resource instanceof Requirement ? [
                'direction' => $this->direction?->value,
                'scope' => $this->scope?->value,
                'participant_kind' => $this->participant_kind?->value,
                'is_active' => $this->is_active,
                'source' => ['name' => $this->name, 'description' => $this->description],
                'translations' => $this->translations ?? (object) [],
            ] : [
                'fulfilled' => $this->fulfilled_at !== null,
            ]),
        ];
    }
}
