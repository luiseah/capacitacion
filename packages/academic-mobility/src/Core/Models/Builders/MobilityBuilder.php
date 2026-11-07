<?php

namespace Module\AcademicMobility\Core\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Models\Mobility;

/**
 * Query builder of {@see Mobility}: named, reusable constraints.
 *
 * @extends Builder<Mobility>
 */
class MobilityBuilder extends Builder
{
    /** Columns the list can be sorted by. */
    public const SORTABLE = ['created_at', 'status', 'participant_type', 'strategy', 'direction', 'scope'];

    /**
     * Apply the list filters present in the request: `status`, `participant_type`, `direction` and `scope`.
     * Missing or invalid filters are ignored.
     */
    public function applyFilters(Request $request): static
    {
        if ($request->has('status')) {
            $this->byStatus($request->enum('status', MobilityStatus::class));
        }

        if ($request->has('participant_type')) {
            $this->byParticipantType($request->string('participant_type')->toString());
        }

        if ($request->has('direction')) {
            $this->byDirection($request->enum('direction', MobilityDirection::class));
        }

        if ($request->has('scope')) {
            $this->byScope($request->enum('scope', MobilityScope::class));
        }

        return $this;
    }

    public function byDirection(?MobilityDirection $direction): static
    {
        return $this->when($direction !== null, fn (self $query) => $query->where('direction', $direction->value));
    }

    public function byScope(?MobilityScope $scope): static
    {
        return $this->when($scope !== null, fn (self $query) => $query->where('scope', $scope->value));
    }

    /**
     * Eager load what the list representation needs.
     */
    public function withListRelations(): static
    {
        return $this->with(['originInstitution', 'destinationInstitution']);
    }

    public function byStatus(?MobilityStatus $status): static
    {
        return $this->when($status !== null, fn (self $query) => $query->where('status', $status->value));
    }

    public function byParticipantType(?string $type): static
    {
        return $this->when(filled($type), fn (self $query) => $query->where('participant_type', $type));
    }

    /**
     * Order by the `sort` map of the request (`sort[created_at]=desc`). Unknown
     * columns are ignored, directions other than `asc` mean `desc`, and it
     * defaults to newest first. Ties are broken by id so pages stay stable.
     */
    public function sort(Request $request): static
    {
        $sort = $request->input('sort');
        $orders = is_array($sort) && $sort !== [] ? $sort : ['created_at' => 'desc'];

        foreach ($orders as $column => $value) {
            if (in_array($column, self::SORTABLE, true)) {
                $this->orderBy($column, strtolower((string) $value) === 'asc' ? 'asc' : 'desc');
            }
        }

        return $this->orderBy('id');
    }
}
