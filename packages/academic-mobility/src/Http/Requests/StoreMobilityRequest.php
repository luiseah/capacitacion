<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Support\ExtensionKind;

class StoreMobilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', config('academic-mobility.models.mobility'));
    }

    /**
     * Strategies are the aliases the host registered. The strategy is the kind of mobility: it
     * may fix the direction, otherwise the form sends it. Whether the direction has a source of
     * participants of the chosen kind is checked when the mobility is created.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participant_type' => ['required', Rule::enum(ParticipantKind::class)],
            'participant_id' => ['required', 'string', 'max:100'],
            'direction' => ['nullable', Rule::enum(MobilityDirection::class)],
            'scope' => ['required', Rule::enum(MobilityScope::class)],
            'institution_id' => ['required', 'string', 'max:26'],
            // The agreement it is made under (optional): one in force with the institution on the other side.
            'agreement_id' => ['nullable', 'string', 'max:26'],
            'strategy' => ['required', 'string', Rule::in(AcademicMobility::aliases(ExtensionKind::Strategy))],
            'metadata' => ['nullable', 'array', 'max:50'],
            'metadata.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
