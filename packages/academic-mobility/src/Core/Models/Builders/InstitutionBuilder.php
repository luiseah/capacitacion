<?php

namespace Module\AcademicMobility\Core\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Query builder of {@see Institution}: named, reusable constraints.
 *
 * @extends Builder<Institution>
 */
class InstitutionBuilder extends Builder
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

        return $this;
    }

    /**
     * Institutions whose name or country code contain every word of the text, ignoring case.
     * A blank text applies no filter.
     */
    public function bySearch(?string $text): static
    {
        foreach (preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $like = '%'.addcslashes(mb_strtolower($token), '%_\\').'%';

            $this->where(fn (Builder $q) => $q
                ->whereRaw("lower(name) like ? escape '\\'", [$like])
                ->orWhereRaw("lower(country) like ? escape '\\'", [$like]));
        }

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
