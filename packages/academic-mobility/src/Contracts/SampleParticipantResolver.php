<?php

namespace Module\AcademicMobility\Contracts;

/**
 * Optional companion of {@see CreatableParticipantResolver}: can invent a person, so the
 * registration form can offer a "fill with random data" button for demos and tests.
 * Only used when `academic-mobility.samples` is on.
 */
interface SampleParticipantResolver extends CreatableParticipantResolver
{
    /**
     * Random values for the fields of {@see fields()}, valid for {@see rules()}.
     *
     * @return array<string, string>
     */
    public function sample(?string $institutionId): array;
}
