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
}
