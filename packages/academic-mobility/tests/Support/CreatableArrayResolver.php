<?php

namespace Module\AcademicMobility\Tests\Support;

use Illuminate\Validation\Rule;
use Module\AcademicMobility\Contracts\SampleParticipantResolver;
use Module\AcademicMobility\Contracts\Participant;

/**
 * Visitors that can be registered on the fly: an in-memory source for the tests.
 */
final class CreatableArrayResolver implements SampleParticipantResolver
{
    /** @var array<string, array<string, mixed>> */
    public array $people = [];

    public function resolve(string|int $id): ?Participant
    {
        return isset($this->people[$id]) ? new FakeParticipant($id, $this->people[$id]) : null;
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false],
        ];
    }

    public function rules(?string $institutionId): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', Rule::notIn(array_column($this->people, 'email'))],
        ];
    }

    public function create(array $data, ?string $institutionId): Participant
    {
        $id = 'V'.(count($this->people) + 1);
        $this->people[$id] = $data + ['institution_id' => $institutionId, 'enrollment' => 'active'];

        return new FakeParticipant($id, $this->people[$id]);
    }

    public function sample(?string $institutionId): array
    {
        return ['name' => 'Sample '.(count($this->people) + 1), 'email' => 'sample'.(count($this->people) + 1).'@x.org'];
    }
}
