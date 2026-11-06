<?php

namespace App\Http\Requests\Requests;

use App\Enums\RequestPriority;
use Illuminate\Validation\Rule;

class PurchaseFormRequest extends ServiceRequestFormRequest
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

            'metadata.item_description' => ['required', 'string', 'max:5000'],
            'metadata.quantity' => ['required', 'integer', 'min:1'],
            'metadata.estimated_amount' => ['required', 'numeric', 'min:0'],
            'metadata.currency' => ['required', Rule::in(['COP', 'USD', 'EUR'])],
            'metadata.supplier' => ['nullable', 'string', 'max:255'],
            'metadata.needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'metadata.justification' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'metadata.item_description' => 'item description',
            'metadata.quantity' => 'quantity',
            'metadata.estimated_amount' => 'estimated amount',
            'metadata.currency' => 'currency',
            'metadata.supplier' => 'supplier',
            'metadata.needed_by' => 'needed by date',
            'metadata.justification' => 'justification',
        ];
    }
}
