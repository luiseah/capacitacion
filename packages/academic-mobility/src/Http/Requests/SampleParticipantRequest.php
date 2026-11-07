<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Module\AcademicMobility\Contracts\SampleParticipantResolver;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Exceptions\UnknownExtension;
use Module\AcademicMobility\Facades\AcademicMobility;

/**
 * Asks the host's resolver to invent a person for the registration form (demos and development).
 */
class SampleParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return config('academic-mobility.samples', false)
            && Gate::forUser($this->user())->allows('create', config('academic-mobility.models.mobility'));
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
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->hasAny(['type', 'direction']) && $this->resolver() === null) {
                $validator->errors()->add('type', __('Participants of this type cannot be registered for this direction.'));
            }
        }];
    }

    public function resolver(): ?SampleParticipantResolver
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

        return $resolver instanceof SampleParticipantResolver ? $resolver : null;
    }
}
