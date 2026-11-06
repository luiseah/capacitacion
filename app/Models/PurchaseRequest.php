<?php

namespace App\Models;

use App\Enums\FieldType;
use App\Enums\RequestTypeKey;
use App\Requests\Field;

class PurchaseRequest extends ServiceRequest
{
    public static function typeKey(): RequestTypeKey
    {
        return RequestTypeKey::Purchase;
    }

    public static function fields(): array
    {
        return [
            new Field('item_description', 'Item description', FieldType::Textarea, required: true),
            new Field('quantity', 'Quantity', FieldType::Number, required: true, rules: ['integer', 'min:1']),
            new Field('estimated_amount', 'Estimated amount', FieldType::Number, required: true, rules: ['min:0']),
            new Field('currency', 'Currency', FieldType::Select, required: true, options: ['COP', 'USD', 'EUR']),
            new Field('supplier', 'Supplier', FieldType::Text),
            new Field('needed_by', 'Needed by', FieldType::Date),
            new Field('justification', 'Justification', FieldType::Textarea, required: true),
        ];
    }

    public static function extraRules(): array
    {
        return [
            'needed_by' => ['after_or_equal:today'],
        ];
    }
}
