<?php

namespace Module\AcademicMobility\Core\Exceptions;

class InvalidRoute extends AcademicMobilityException
{
    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function noLocalInstitution(): self
    {
        return new self(__('There is no local institution yet: create it before creating mobilities.'), 'route');
    }

    public static function counterpartIsLocal(): self
    {
        return new self(__('The other institution cannot be the local institution.'), 'institution_id');
    }

    public static function directionRequired(string $strategy): self
    {
        return new self(__('The strategy [:strategy] serves both directions: choose incoming or outgoing.', ['strategy' => $strategy]), 'direction');
    }

    public static function directionNotAllowed(string $strategy, string $expected): self
    {
        return new self(__('The strategy [:strategy] is only for :expected mobilities.', ['strategy' => $strategy, 'expected' => __($expected)]), 'direction');
    }

    public static function scopeMismatch(string $selected, string $country): self
    {
        return new self(
            $selected === 'national'
                ? __('A national mobility needs an institution from the local country (:country).', ['country' => $country])
                : __('An international mobility needs an institution outside the local country (:country).', ['country' => $country]),
            'institution_id',
        );
    }

    public static function participantFromAnotherInstitution(): self
    {
        return new self(__('The participant does not belong to the origin institution.'), 'participant_id');
    }
}
