<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\AcademicMobility\Core\Enums\MobilityScope;

class IndexInstitutionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', config('academic-mobility.models.institution'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::enum(MobilityScope::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
