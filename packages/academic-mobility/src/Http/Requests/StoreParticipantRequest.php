<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Module\AcademicMobility\Contracts\CreatableParticipantResolver;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Exceptions\UnknownExtension;
use Module\AcademicMobility\Facades\AcademicMobility;

/**
 * Registers a person that is not there yet (a visitor of an incoming mobility). What to ask for
 * and how to validate it comes from the host's resolver of that direction and kind.
 */
class StoreParticipantRequest extends FormRequest
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
        $rules = [
            'type' => ['required', Rule::enum(ParticipantKind::class)],
            'direction' => ['required', Rule::enum(MobilityDirection::class)],
            'institution_id' => ['nullable', 'string', 'max:26', 'required_if:direction,incoming'],
            'data' => ['required', 'array'],
        ];

        foreach ($this->resolver()?->rules($this->input('institution_id')) ?? [] as $field => $fieldRules) {
            $rules["data.{$field}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['type', 'direction']) || $this->resolver() !== null) {
                return;
            }

            $validator->errors()->add('type', __('Participants of this type cannot be registered for this direction.'));
        }, function (Validator $validator) {
            $institution = $this->input('institution_id');

            if (is_string($institution) && $institution !== ''
                && ! config('academic-mobility.models.institution')::query()->whereKey($institution)->exists()) {
                $validator->errors()->add('institution_id', __('The institution was not found.'));
            }
        }];
    }

    public function resolver(): ?CreatableParticipantResolver
    {
        $direction = MobilityDirection::tryFrom((string) $this->input('direction'));
        $kind = ParticipantKind::tryFrom((string) $this->input('type'));

        if ($direction === null || $kind === null) {
            return null;
        }

        try {
            $resolver = AcademicMobility::participantResolver($direction, $kind->value);
        } catch (UnknownExtension) {
            return null;
        }

        return $resolver instanceof CreatableParticipantResolver ? $resolver : null;
    }
}
