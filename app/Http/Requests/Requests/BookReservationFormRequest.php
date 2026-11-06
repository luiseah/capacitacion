<?php

namespace App\Http\Requests\Requests;

use App\Enums\RequestPriority;
use Illuminate\Validation\Rule;

class BookReservationFormRequest extends ServiceRequestFormRequest
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

            'metadata.book_title' => ['required', 'string', 'max:255'],
            'metadata.author' => ['nullable', 'string', 'max:255'],
            'metadata.isbn' => ['nullable', 'string', 'max:20'],
            'metadata.copies' => ['required', 'integer', 'min:1'],
            'metadata.pickup_date' => ['required', 'date', 'after_or_equal:today'],
            'metadata.return_date' => ['required', 'date', 'after:metadata.pickup_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'metadata.book_title' => 'book title',
            'metadata.author' => 'author',
            'metadata.isbn' => 'ISBN',
            'metadata.copies' => 'copies',
            'metadata.pickup_date' => 'pickup date',
            'metadata.return_date' => 'return date',
        ];
    }
}
