<?php

namespace Module\AcademicMobility\Contracts;

/**
 * Optional companion of {@see ParticipantResolver}: the creation form can register a person that
 * does not exist yet (typically a visitor of an incoming mobility). The host describes the fields
 * to ask for and how to validate and store them; the package never sees its tables.
 */
interface CreatableParticipantResolver extends ParticipantResolver
{
    /**
     * The inputs of the registration form, in order. Labels should already be translated.
     *
     * @return array<int, array{name: string, label: string, type?: string, required?: bool}>
     */
    public function fields(): array;

    /**
     * Validation rules keyed by field name (validated under `data`), e.g. `['document_number' => ['required']]`.
     *
     * @param  string|null  $institutionId  The origin institution, for visitors.
     * @return array<string, mixed>
     */
    public function rules(?string $institutionId): array;

    /**
     * @param  array<string, mixed>  $data  Validated by {@see rules()}.
     */
    public function create(array $data, ?string $institutionId): Participant;
}
