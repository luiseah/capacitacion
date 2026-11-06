<?php

namespace App\Requests\Strategies;

use App\Models\ServiceRequest;
use App\Models\User;

interface RequestStrategy
{
    /**
     * Request model class that declares the fields and rules of this type.
     *
     * @return class-string<ServiceRequest>
     */
    public function requestModel(): string;

    /**
     * Validate the metadata against the request model and return the validated data.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $metadata): array;

    /**
     * Persist a new request of this type and optionally submit it.
     *
     * @param  array{title: string, description?: ?string, priority: string}  $attributes
     * @param  array<string, mixed>  $metadata  Already validated by `validate()`.
     */
    public function create(User $requester, array $attributes, array $metadata, bool $submit = false): ServiceRequest;

    /**
     * Move a draft request to the submitted state and run type-specific logic.
     */
    public function submit(ServiceRequest $request): void;
}
