<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;

/**
 * @mixin ServiceRequest
 */
class ServiceRequestDetailResource extends ServiceRequestResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata ?? [];

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'abilities' => [
                'update' => (bool) $request->user()?->can('update', $this->resource),
                'delete' => (bool) $request->user()?->can('delete', $this->resource),
            ],
            'details' => array_map(fn ($field) => [
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type->value,
                'value' => $metadata[$field->key] ?? null,
            ], $this->type->key->strategy()->requestModel()::fields()),
        ];
    }
}
