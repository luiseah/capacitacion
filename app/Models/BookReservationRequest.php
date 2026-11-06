<?php

namespace App\Models;

use App\Enums\FieldType;
use App\Enums\RequestTypeKey;
use App\Requests\Field;

class BookReservationRequest extends ServiceRequest
{
    public static function typeKey(): RequestTypeKey
    {
        return RequestTypeKey::BookReservation;
    }

    public static function fields(): array
    {
        return [
            new Field('book_title', 'Book title', FieldType::Text, required: true),
            new Field('author', 'Author', FieldType::Text),
            new Field('isbn', 'ISBN', FieldType::Text, rules: ['max:20']),
            new Field('copies', 'Copies', FieldType::Number, required: true, rules: ['integer', 'min:1']),
            new Field('pickup_date', 'Pickup date', FieldType::Date, required: true),
            new Field('return_date', 'Return date', FieldType::Date, required: true),
        ];
    }

    public static function extraRules(): array
    {
        return [
            'pickup_date' => ['after_or_equal:today'],
            'return_date' => ['after:pickup_date'],
        ];
    }
}
