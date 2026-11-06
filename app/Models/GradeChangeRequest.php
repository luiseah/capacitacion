<?php

namespace App\Models;

use App\Enums\FieldType;
use App\Enums\RequestTypeKey;
use App\Requests\Field;

class GradeChangeRequest extends ServiceRequest
{
    public static function typeKey(): RequestTypeKey
    {
        return RequestTypeKey::GradeChange;
    }

    public static function fields(): array
    {
        return [
            new Field('student_name', 'Student name', FieldType::Text, required: true),
            new Field('course', 'Course', FieldType::Text, required: true),
            new Field('academic_period', 'Academic period', FieldType::Text, required: true),
            new Field('current_grade', 'Current grade', FieldType::Number, required: true),
            new Field('requested_grade', 'Requested grade', FieldType::Number, required: true),
            new Field('reason', 'Reason', FieldType::Textarea, required: true),
        ];
    }
}
