<?php

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Email = 'email';
    case Date = 'date';
    case DateTime = 'datetime';
    case Select = 'select';
    case Boolean = 'boolean';

    /**
     * Base validation rules implied by the field type.
     *
     * @param  array<int, string>  $options  Allowed values for select fields.
     * @return array<int, string>
     */
    public function baseRules(array $options = []): array
    {
        return match ($this) {
            self::Text => ['string', 'max:255'],
            self::Textarea => ['string', 'max:5000'],
            self::Number => ['numeric'],
            self::Email => ['email', 'max:255'],
            self::Date => ['date'],
            self::DateTime => ['date'],
            self::Select => ['string', 'in:'.implode(',', $options)],
            self::Boolean => ['boolean'],
        };
    }
}
