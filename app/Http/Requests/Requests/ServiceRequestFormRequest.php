<?php

namespace App\Http\Requests\Requests;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Base for the form requests that create a request of a concrete type. It only
 * holds what is shared; every concrete class declares its own rules explicitly.
 */
abstract class ServiceRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $existing = $this->route('serviceRequest');

        // Editing binds the request from the URL; creating binds the `{type}` enum.
        return $existing instanceof ServiceRequest
            ? Gate::inspect('update', $existing)->allowed()
            : Gate::inspect('create', [ServiceRequest::class, $this->route('type')])->allowed();
    }

    public function shouldSubmit(): bool
    {
        return $this->validated('action') === 'submit';
    }

    /**
     * @return array{title: string, description?: ?string, priority: string}
     */
    public function requestAttributes(): array
    {
        return $this->safe()->only(['title', 'description', 'priority']);
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->validated('metadata') ?? [];
    }
}
