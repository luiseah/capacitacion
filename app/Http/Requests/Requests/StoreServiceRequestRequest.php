<?php

namespace App\Http\Requests\Requests;

use App\Enums\RequestPriority;
use App\Enums\RequestTypeKey;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreServiceRequestRequest extends FormRequest
{
    /** @var array<string, mixed> */
    private array $validatedMetadata = [];

    public function authorize(): bool
    {
        return Gate::allows('create', ServiceRequest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(RequestPriority::class)],
            'action' => ['nullable', Rule::in(['draft', 'submit'])],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Validate the metadata with the strategy of the chosen type. Errors are
     * reported under `metadata.<field>` so the form can show them per input.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('metadata')) {
                    return;
                }

                try {
                    $this->validatedMetadata = $this->typeKey()->strategy()->validate($this->input('metadata', []));
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add("metadata.$field", $message);
                        }
                    }
                }
            },
        ];
    }

    public function typeKey(): RequestTypeKey
    {
        // Resolved by implicit enum route binding of the `{type}` URL segment.
        return $this->route('type');
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->validatedMetadata;
    }
}
