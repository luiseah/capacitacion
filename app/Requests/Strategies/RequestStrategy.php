<?php

namespace App\Requests\Strategies;

use App\Http\Requests\Requests\ServiceRequestFormRequest;
use App\Models\ServiceRequest;

interface RequestStrategy
{
    /**
     * Request model class that declares the fields and rules of this type.
     *
     * @return class-string<ServiceRequest>
     */
    public function requestModel(): string;

    /**
     * Form request that authorizes and validates the creation of this type.
     *
     * @return class-string<ServiceRequestFormRequest>
     */
    public function formRequest(): string;

    /**
     * Persist a new request of this type from an already validated form request
     * and optionally submit it.
     */
    public function create(ServiceRequestFormRequest $request): ServiceRequest;

    /**
     * Update an existing request from an already validated form request. A
     * draft can be submitted in the same step.
     */
    public function update(ServiceRequest $request, ServiceRequestFormRequest $form): ServiceRequest;

    /**
     * Move a draft request to the submitted state and run type-specific logic.
     */
    public function submit(ServiceRequest $request): void;
}
