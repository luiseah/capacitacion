<?php

namespace Module\Agreements\Models\Builders;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Models\Institution;
use Module\Agreements\Enums\AgreementActivity;
use Module\Agreements\Enums\AgreementParties;
use Module\Agreements\Enums\AgreementRelation;
use Module\Agreements\Enums\AgreementStatus;
use Module\Agreements\Enums\AgreementType;
use Module\Agreements\Models\Agreement;
use Module\Pagination\PaginatedBuilder;

/**
 * Query builder of {@see Agreement}: named, reusable constraints.
 *
 * @extends PaginatedBuilder<Agreement>
 */
class AgreementBuilder extends PaginatedBuilder
{
    /**
     * Apply the list filters present in the request: `search` (name or reference), `institution_id`,
     * `type`, `parties`, `status` (on today's date), `scope` and `activity`. Blank filters are ignored.
     */
    public function applyFilters(Request $request): static
    {
        if ($request->has('search')) {
            $this->bySearch($request->string('search')->toString());
        }

        if ($request->filled('institution_id')) {
            $this->forInstitution($request->string('institution_id')->toString());
        }

        if ($request->has('type')) {
            $this->when($request->enum('type', AgreementType::class), fn (self $q, AgreementType $type) => $q->ofType($type));
        }

        if ($request->has('scope')) {
            $this->byScope($request->enum('scope', MobilityScope::class));
        }

        if ($request->has('parties')) {
            $this->when($request->enum('parties', AgreementParties::class), fn (self $q, AgreementParties $parties) => $q->ofParties($parties));
        }

        if ($request->has('status')) {
            $this->when($request->enum('status', AgreementStatus::class), fn (self $q, AgreementStatus $status) => $q->withStatus($status));
        }

        if ($request->has('activity')) {
            $this->when($request->enum('activity', AgreementActivity::class), fn (self $q, AgreementActivity $activity) => $q->withActivity($activity));
        }

        $this->loadRelations($request->array('with'));

        return $this->sort($request);
    }

    protected function relationEnum(): ?string
    {
        return AgreementRelation::class;
    }

    protected function sortable(): array
    {
        return ['name' => 'name', 'starts_on' => 'starts_on', 'ends_on' => 'ends_on', 'status' => 'status', 'created_at' => 'created_at'];
    }

    protected function defaultOrder(): void
    {
        $this->orderByDesc('starts_on')->orderBy('name');
    }

    /** Agreements whose name or reference contain every word of the text, ignoring case and accents. */
    public function bySearch(?string $text): static
    {
        $this->whereSearch(['name', 'reference'], $text);

        return $this;
    }

    /**
     * Agreements in force on the date (today by default): recorded as in force, started, and not ended yet
     * or without an end date.
     */
    public function inForce(?CarbonInterface $date = null): static
    {
        $day = ($date ?? now())->toDateString();

        return $this->where('status', AgreementStatus::Active->value)
            ->whereDate('starts_on', '<=', $day)
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day));
    }

    /** Agreements that ended: recorded as expired, or in force with an end date before the date (today by default). */
    public function expired(?CarbonInterface $date = null): static
    {
        return $this->where(fn (Builder $q) => $q
            ->where('status', AgreementStatus::Expired->value)
            ->orWhere(fn (Builder $active) => $active
                ->where('status', AgreementStatus::Active->value)
                ->whereNotNull('ends_on')
                ->whereDate('ends_on', '<', ($date ?? now())->toDateString())));
    }

    /**
     * Agreements that stand in that status on the date (today by default): the ones in force and the expired
     * ones follow the dates; the others are as recorded.
     */
    public function withStatus(AgreementStatus $status, ?CarbonInterface $date = null): static
    {
        return match ($status) {
            AgreementStatus::Active => $this->inForce($date),
            AgreementStatus::Expired => $this->expired($date),
            default => $this->where('status', $status->value),
        };
    }

    /**
     * Agreements national (every institution of the agreement in the local country) or international (any
     * institution elsewhere). Without a local institution nothing matches. A null scope applies no filter.
     */
    public function byScope(?MobilityScope $scope): static
    {
        if ($scope === null) {
            return $this;
        }

        $institution = config('academic-mobility.models.institution', Institution::class);
        $localCountry = fn () => $institution::query()->where('is_local', true)->select('country')->limit(1);

        $abroad = fn (Builder $q) => $q->whereHas('institution', fn (Builder $i) => $i->where('country', '!=', $localCountry()))
            ->orWhereHas('institutions', fn (Builder $i) => $i->where('country', '!=', $localCountry()));

        return $scope === MobilityScope::International
            ? $this->where($abroad)
            : $this->whereHas('institution', fn (Builder $i) => $i->where('country', '=', $localCountry()))
                ->whereDoesntHave('institutions', fn (Builder $i) => $i->where('country', '!=', $localCountry()));
    }

    /** Agreements that cover the activity (they can cover several). */
    public function withActivity(AgreementActivity $activity): static
    {
        return $this->whereJsonContains('activities', $activity->value);
    }

    public function ofType(AgreementType $type): static
    {
        return $this->where('type', $type->value);
    }

    public function frameworks(): static
    {
        return $this->ofType(AgreementType::Framework);
    }

    public function specifics(): static
    {
        return $this->ofType(AgreementType::Specific);
    }

    public function ofParties(AgreementParties $parties): static
    {
        return $this->where('parties', $parties->value);
    }

    /**
     * The agreements the institution takes part in: its own and the multilateral ones that include it.
     */
    public function forInstitution(string $institutionId): static
    {
        return $this->where(fn (Builder $q) => $q
            ->where('institution_id', $institutionId)
            ->orWhereHas('institutions', fn (Builder $institutions) => $institutions->whereKey($institutionId)));
    }

    /** The latest first. */
    public function latestFirst(): static
    {
        return $this->orderByDesc('starts_on')->orderBy('name')->orderBy('id');
    }
}
