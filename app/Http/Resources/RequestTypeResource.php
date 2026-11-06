<?php

namespace App\Http\Resources;

use App\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestType
 */
class RequestTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key->value,
            'name' => $this->key->label(),
            'description' => $this->description,
            'fields' => array_map(
                fn ($field) => $field->toArray(),
                $this->key->strategy()->requestModel()::fields(),
            ),
        ];
    }
}
