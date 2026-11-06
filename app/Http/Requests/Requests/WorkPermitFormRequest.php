<?php

namespace App\Http\Requests\Requests;

use App\Enums\RequestPriority;
use Illuminate\Validation\Rule;

class WorkPermitFormRequest extends ServiceRequestFormRequest
{
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
            'metadata' => ['required', 'array'],

            'metadata.permit_type' => ['required', Rule::in(['medical', 'personal', 'study', 'bereavement', 'other'])],
            'metadata.start_at' => ['required', 'date'],
            'metadata.end_at' => ['required', 'date', 'after:metadata.start_at'],
            'metadata.reason' => ['required', 'string', 'max:5000'],
            'metadata.has_supporting_document' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'metadata.permit_type' => 'permit type',
            'metadata.start_at' => 'start',
            'metadata.end_at' => 'end',
            'metadata.reason' => 'reason',
            'metadata.has_supporting_document' => 'supporting document',
        ];
    }
}
