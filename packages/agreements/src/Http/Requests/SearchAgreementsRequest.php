<?php

namespace Module\Agreements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The agreements of the institution chosen in the creation form of a mobility.
 */
class SearchAgreementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gate = Gate::forUser($this->user());
        $mobility = config('academic-mobility.models.mobility');

        return $gate->allows('create', $mobility) || $gate->allows('viewAny', $mobility);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['institution_id' => ['required', 'string', 'max:26']];
    }
}
