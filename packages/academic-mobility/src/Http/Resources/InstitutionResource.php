<?php

namespace Module\AcademicMobility\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * @mixin Institution
 */
class InstitutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country' => $this->country,
            'is_local' => $this->is_local,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
