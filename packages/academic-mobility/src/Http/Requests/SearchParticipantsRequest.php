<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Facades\AcademicMobility;

/**
 * The picker of the creation form: who can take part, given the direction (own people leave,
 * visitors arrive) and, for visitors, the origin institution they come from.
 */
class SearchParticipantsRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ParticipantKind::class)],
            'direction' => ['required', Rule::enum(MobilityDirection::class)],
            'institution_id' => ['nullable', 'string', 'max:26', 'required_if:direction,incoming'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $direction = MobilityDirection::tryFrom((string) $this->input('direction'));

            if ($direction !== null && ! $validator->errors()->has('type')
                && ! in_array($this->input('type'), AcademicMobility::participantKinds($direction), true)) {
                $validator->errors()->add('type', __('There is no source of participants of this type for this direction.'));
            }
        }];
    }
}
