<?php

namespace App\Http\Requests\Requests;

use App\Enums\RequestPriority;
use Illuminate\Validation\Rule;

class GradeChangeFormRequest extends ServiceRequestFormRequest
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

            'metadata.student_name' => ['required', 'string', 'max:255'],
            'metadata.course' => ['required', 'string', 'max:255'],
            'metadata.academic_period' => ['required', 'string', 'max:255'],
            'metadata.current_grade' => ['required', 'numeric', 'min:0'],
            'metadata.requested_grade' => ['required', 'numeric', 'min:0', 'different:metadata.current_grade'],
            'metadata.reason' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'metadata.student_name' => 'student name',
            'metadata.course' => 'course',
            'metadata.academic_period' => 'academic period',
            'metadata.current_grade' => 'current grade',
            'metadata.requested_grade' => 'requested grade',
            'metadata.reason' => 'reason',
        ];
    }
}
