<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\ParticipantKind;

/**
 * What a mobility with this direction, scope and participant kind will have to bring: shown
 * in the creation form before the request exists.
 */
class PreviewRequirementsRequest extends FormRequest
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
            'direction' => ['required', Rule::enum(MobilityDirection::class)],
            'scope' => ['required', Rule::enum(MobilityScope::class)],
            'type' => ['required', Rule::enum(ParticipantKind::class)],
        ];
    }
}
