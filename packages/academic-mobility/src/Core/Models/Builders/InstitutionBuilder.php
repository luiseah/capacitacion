<?php

namespace Module\AcademicMobility\Core\Models\Builders;

use Illuminate\Http\Request;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Models\Institution;
use Module\Pagination\PaginatedBuilder;

/**
 * Query builder of {@see Institution}: named, reusable constraints.
 *
 * @extends PaginatedBuilder<Institution>
 */
class InstitutionBuilder extends PaginatedBuilder
{
    /**
     * Apply the filters present in the request: `search` (name or country code) and `scope`
     * (`national` = same country as the local institution). Blank filters are ignored.
     */
    public function applyFilters(Request $request): static
    {
        if ($request->has('search')) {
            $this->bySearch($request->string('search')->toString());
        }

        if ($request->has('scope')) {
            $this->byScope($request->enum('scope', MobilityScope::class));
        }

        return $this->sort($request);
    }

    protected function sortable(): array
    {
        return ['name' => 'name', 'country' => 'country'];
    }

    protected function defaultOrder(): void
    {
        $this->orderByDesc('is_local')->orderBy('name');
    }

    /**
     * Institutions whose name or country code contain every word of the text, ignoring case and accents.
     * A blank text applies no filter.
     */
    public function bySearch(?string $text): static
    {
        $this->whereSearch(['name', 'country'], $text);

        return $this;
    }

    /**
     * Institutions national (same country as the local one) or international (any other) to the
     * local institution. Without a local institution nothing matches. A null scope applies no filter.
     */
    public function byScope(?MobilityScope $scope): static
    {
        if ($scope === null) {
            return $this;
        }

        $localCountry = $this->getModel()->newQuery()->where('is_local', true)->select('country')->limit(1);

        return $this->where('country', $scope === MobilityScope::National ? '=' : '!=', $localCountry);
    }

    /**
     * Every institution but the local one: who a mobility can come from or go to.
     */
    public function partners(): static
    {
        return $this->where('is_local', false);
    }

    /**
     * The local institution first, then by name.
     */
    public function localFirst(): static
    {
        return $this->orderByDesc('is_local')->orderBy('name')->orderBy('id');
    }
}
