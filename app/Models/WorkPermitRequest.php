<?php

namespace App\Models;

use App\Enums\FieldType;
use App\Enums\RequestTypeKey;
use App\Requests\Field;

class WorkPermitRequest extends ServiceRequest
{
    public static function typeKey(): RequestTypeKey
    {
        return RequestTypeKey::WorkPermit;
    }

    public static function fields(): array
    {
        return [
            new Field('permit_type', 'Permit type', FieldType::Select, required: true, options: ['medical', 'personal', 'study', 'bereavement', 'other']),
            new Field('start_at', 'Start', FieldType::DateTime, required: true),
            new Field('end_at', 'End', FieldType::DateTime, required: true),
            new Field('reason', 'Reason', FieldType::Textarea, required: true),
            new Field('has_supporting_document', 'Has supporting document', FieldType::Boolean),
        ];
    }
}
