<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\ParticipantKind;

/**
 * Creates or updates a requirement of the catalog (the same rules for both).
 */
class RequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requirement = $this->route('requirement');

        return Gate::forUser($this->user())->allows(
            $requirement === null ? 'create' : 'update',
            $requirement ?? config('academic-mobility.models.requirement'),
        );
    }

    /**
     * An empty `direction`, `scope` or `participant_kind` means "any".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'direction' => ['nullable', Rule::enum(MobilityDirection::class)],
            'scope' => ['nullable', Rule::enum(MobilityScope::class)],
            'participant_kind' => ['nullable', Rule::enum(ParticipantKind::class)],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:150'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Only the filled-in translations are stored; empty criteria mean "any".
     *
     * @return array<string, mixed>
     */
    public function attributesToStore(): array
    {
        // The form always sends the whole requirement (PUT replaces it): a criterion left out means "any".
        $data = [...['direction' => null, 'scope' => null, 'participant_kind' => null], ...$this->validated()];

        $data['translations'] = collect($data['translations'] ?? [])
            ->map(fn (array $texts) => array_filter($texts, fn ($text) => filled($text)))
            ->filter()
            ->all() ?: null;

        return $data;
    }
}
