<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The agreements of the institution chosen in the creation form of a mobility.
 */
class SearchAgreementsRequest extends FormRequest
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
        return ['institution_id' => ['required', 'string', 'max:26']];
    }
}
