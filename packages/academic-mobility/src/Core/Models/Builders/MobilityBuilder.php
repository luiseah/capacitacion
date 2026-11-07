<?php

namespace Module\AcademicMobility\Core\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityRelation;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Models\Mobility;
use Module\Pagination\PaginatedBuilder;

/**
 * Query builder of {@see Mobility}: named, reusable constraints.
 *
 * @extends PaginatedBuilder<Mobility>
 */
class MobilityBuilder extends PaginatedBuilder
{
    /**
     * Apply the list filters present in the request: `status`, `participant_type`, `direction` and `scope`,
     * then `with` (relations, see {@see relationEnum()}) and `sort`.
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

        $this->loadRelations($request->array('with'));

        return $this->sort($request);
    }

    protected function relationEnum(): ?string
    {
        return MobilityRelation::class;
    }

    protected function sortable(): array
    {
        return array_combine(
            ['created_at', 'status', 'participant_type', 'strategy', 'direction', 'scope'],
            ['created_at', 'status', 'participant_type', 'strategy', 'direction', 'scope'],
        );
    }

    protected function defaultOrder(): void
    {
        $this->orderByDesc('created_at');
    }

    public function byDirection(?MobilityDirection $direction): static
    {
        return $this->when($direction !== null, fn (self $query) => $query->where('direction', $direction->value));
    }

    public function byScope(?MobilityScope $scope): static
    {
        return $this->when($scope !== null, fn (self $query) => $query->where('scope', $scope->value));
    }

    public function byStatus(?MobilityStatus $status): static
    {
        return $this->when($status !== null, fn (self $query) => $query->where('status', $status->value));
    }

    public function byParticipantType(?string $type): static
    {
        return $this->when(filled($type), fn (self $query) => $query->where('participant_type', $type));
    }
}
