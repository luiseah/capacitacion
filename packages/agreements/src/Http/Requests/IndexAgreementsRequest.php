<?php

namespace Module\Agreements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\Agreements\Enums\AgreementActivity;
use Module\Agreements\Enums\AgreementParties;
use Module\Agreements\Enums\AgreementRelation;
use Module\Agreements\Enums\AgreementStatus;
use Module\Agreements\Enums\AgreementType;
use Module\Pagination\Enums\SortDirection;

class IndexAgreementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', config('agreements.models.agreement'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'institution_id' => ['nullable', 'string', 'max:26'],
            'type' => ['nullable', Rule::enum(AgreementType::class)],
            'parties' => ['nullable', Rule::enum(AgreementParties::class)],
            'status' => ['nullable', Rule::enum(AgreementStatus::class)],
            'scope' => ['nullable', Rule::enum(MobilityScope::class)],
            'activity' => ['nullable', Rule::enum(AgreementActivity::class)],
            'with' => ['nullable', 'array'],
            'with.*' => ['distinct', Rule::enum(AgreementRelation::class)],
            'sort' => ['nullable', 'array'],
            'sort.*' => [Rule::enum(SortDirection::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
