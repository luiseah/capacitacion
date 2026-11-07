<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\AcademicMobility\Core\Enums\MobilityScope;

/**
 * The institution picker of the creation form: partners of the chosen scope matching a search.
 */
class SearchInstitutionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', config('academic-mobility.models.mobility'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', Rule::enum(MobilityScope::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
