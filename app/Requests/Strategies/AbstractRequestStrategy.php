<?php

namespace App\Requests\Strategies;

use App\Enums\RequestStatus;
use App\Http\Requests\Requests\ServiceRequestFormRequest;
use App\Models\ServiceRequest;

abstract class AbstractRequestStrategy implements RequestStrategy
{
    public function create(ServiceRequestFormRequest $request): ServiceRequest
    {
        $attributes = $request->requestAttributes();

        $serviceRequest = $this->requestModel()::create([
            'requester_id' => $request->user()->id,
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'priority' => $attributes['priority'],
            'status' => RequestStatus::Draft,
            'metadata' => $request->metadata(),
        ]);

        if ($request->shouldSubmit()) {
            $this->submit($serviceRequest);
        }

        return $serviceRequest;
    }

    public function update(ServiceRequest $request, ServiceRequestFormRequest $form): ServiceRequest
    {
        $attributes = $form->requestAttributes();

        $request->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'priority' => $attributes['priority'],
            'metadata' => $form->metadata(),
        ]);

        if ($form->shouldSubmit() && $request->status === RequestStatus::Draft) {
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
