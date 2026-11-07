<?php

namespace Module\Agreements\Tests\Support;

use Module\AcademicMobility\Contracts\Participant;
use Module\AcademicMobility\Contracts\SearchableParticipantResolver;

final class ArrayParticipantResolver implements SearchableParticipantResolver
{
    /**
     * @param  array<string|int, array<string, mixed>>  $people  id => attributes
     */
    public function __construct(private readonly array $people, private readonly string $type = 'student') {}

    public function resolve(string|int $id): ?Participant
    {
        return isset($this->people[$id]) ? new FakeParticipant($id, $this->people[$id], $this->type) : null;
    }

    public function search(string $term, int $limit = 10, ?string $institutionId = null): array
    {
        $found = array_filter(
            array_keys($this->people),
            fn (string|int $id) => ($term === '' || str_contains((new FakeParticipant($id, [], $this->type))->displayName(), $term))
                && ($institutionId === null || ($this->people[$id]['institution_id'] ?? null) === $institutionId),
        );

        return array_map(fn (string|int $id) => new FakeParticipant($id, $this->people[$id], $this->type), array_slice(array_values($found), 0, $limit));
    }
}
