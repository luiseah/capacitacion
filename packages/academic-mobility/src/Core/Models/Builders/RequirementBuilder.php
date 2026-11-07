<?php

namespace Module\AcademicMobility\Core\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Requirement;

/**
 * Query builder of {@see Requirement}: named, reusable constraints.
 *
 * @extends Builder<Requirement>
 */
class RequirementBuilder extends Builder
{
    /**
     * Apply the list filters present in the request: `search` (name), `direction`, `scope`,
     * `participant_kind` (each one matches the requirements that name it) and `active`.
     */
    public function applyFilters(Request $request): static
    {
        if ($request->has('search')) {
            $this->bySearch($request->string('search')->toString());
        }

        if ($request->has('direction')) {
            $this->when($request->enum('direction', MobilityDirection::class), fn (self $q, $direction) => $q->where('direction', $direction->value));
        }

        if ($request->has('scope')) {
            $this->when($request->enum('scope', MobilityScope::class), fn (self $q, $scope) => $q->where('scope', $scope->value));
        }

        if ($request->has('participant_kind')) {
            $this->when($request->enum('participant_kind', ParticipantKind::class), fn (self $q, $kind) => $q->where('participant_kind', $kind->value));
        }

        if ($request->has('active') && $request->filled('active')) {
            $this->where('is_active', $request->boolean('active'));
        }

        return $this;
    }

    public function bySearch(?string $text): static
    {
        foreach (preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $this->whereRaw("lower(name) like ? escape '\\'", ['%'.addcslashes(mb_strtolower($token), '%_\\').'%']);
        }

        return $this;
    }

    /**
     * The active requirements of a mobility with this direction, scope and participant kind: every
     * criterion a requirement names has to match, and one it leaves empty matches anything.
     */
    public function applicableTo(MobilityDirection $direction, MobilityScope $scope, ParticipantKind|string $kind): static
    {
        $kind = $kind instanceof ParticipantKind ? $kind->value : $kind;

        return $this->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('direction')->orWhere('direction', $direction->value))
            ->where(fn (Builder $q) => $q->whereNull('scope')->orWhere('scope', $scope->value))
            ->where(fn (Builder $q) => $q->whereNull('participant_kind')->orWhere('participant_kind', $kind));
    }

    public function ordered(): static
    {
        return $this->orderBy('position')->orderBy('name')->orderBy('id');
    }
}
