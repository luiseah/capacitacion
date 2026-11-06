<?php

namespace App\Requests\Strategies;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;

abstract class AbstractRequestStrategy implements RequestStrategy
{
    public function validate(array $metadata): array
    {
        return $this->requestModel()::validateMetadata($metadata);
    }

    public function create(User $requester, array $attributes, array $metadata, bool $submit = false): ServiceRequest
    {
        $request = $this->requestModel()::create([
            'requester_id' => $requester->id,
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'priority' => $attributes['priority'],
            'status' => RequestStatus::Draft,
            'metadata' => $metadata,
        ]);

        if ($submit) {
            $this->submit($request);
        }

        return $request;
    }

    public function submit(ServiceRequest $request): void
    {
        $request->forceFill([
            'status' => RequestStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        $this->afterSubmit($request);
    }

    protected function afterSubmit(ServiceRequest $request): void
    {
        //
    }
}
