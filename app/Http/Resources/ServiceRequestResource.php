<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'type' => $this->type->key->label(),
            'type_key' => $this->type->key->value,
            'requester' => $this->requester->name,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
