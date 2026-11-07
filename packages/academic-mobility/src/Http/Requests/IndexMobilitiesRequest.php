<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityRelation;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\Pagination\Enums\SortDirection;

class IndexMobilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('viewAny', config('academic-mobility.models.mobility'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(MobilityStatus::class)],
            'participant_type' => ['nullable', Rule::enum(ParticipantKind::class)],
            'direction' => ['nullable', Rule::enum(MobilityDirection::class)],
            'scope' => ['nullable', Rule::enum(MobilityScope::class)],
            'with' => ['nullable', 'array'],
            'with.*' => ['distinct', Rule::enum(MobilityRelation::class)],
            'sort' => ['nullable', 'array'],
            'sort.*' => [Rule::enum(SortDirection::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Reject unknown sort columns instead of ignoring them silently.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function ($validator) {
            $unknown = array_diff(array_keys((array) $this->input('sort', [])), config('academic-mobility.models.mobility')::query()->sortableNames());

            if ($unknown !== []) {
                $validator->errors()->add('sort', __('Unknown sort column: :columns.', ['columns' => implode(', ', $unknown)]));
            }
        }];
    }
}
