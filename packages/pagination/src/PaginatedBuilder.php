<?php

namespace Module\Pagination;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Request;
use Module\Pagination\Enums\SortDirection;
use Module\Pagination\Search\SearchInput;

/**
 * Base of the custom Eloquent builders: pagination driven by the request.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class PaginatedBuilder extends Builder
{
    /** The most rows a client can ask for in one page. */
    public const MAX_PER_PAGE = 100;

    /**
     * Paginates when the request asks for it (`per_page` or `page`), else returns every row.
     *
     * @return LengthAwarePaginator<int, TModel>|Collection<int, TModel>
     */
    public function pagination(int $count = 15): LengthAwarePaginator|Collection
    {
        return Request::hasAny(['per_page', 'page'])
            ? $this->paginated($count)
            : $this->get();
    }

    /**
     * Always paginates: `per_page` of the request (between 1 and {@see MAX_PER_PAGE}) or `$count`, keeping the query string in the links.
     *
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginated(int $count = 15): LengthAwarePaginator
    {
        $perPage = max(1, min(Request::integer('per_page', $count), static::MAX_PER_PAGE));

        return $this->paginate($perPage)->withQueryString();
    }

    /**
     * Rows where every word of the text is in any of the columns, ignoring case and accents (the `search()`
     * SQL function): "jose perez" finds "José" in `name` and "Pérez" in `lastname`. A blank text applies no filter.
     *
     * @param  array<int, string>  $columns
     */
    public function whereSearch(array $columns, ?string $text): static
    {
        foreach (SearchInput::tokens((string) $text) as $token) {
            $this->where(fn (Builder $query) => $query->orWhereSearch($columns, $token));
        }

        return $this;
    }

    /**
     * Adds an OR: the word (not split) is in any of the columns, ignoring case and accents.
     *
     * @param  array<int, string>  $columns
     */
    public function orWhereSearch(array $columns, string $token): static
    {
        $pattern = SearchInput::like($token);

        foreach ($columns as $column) {
            $this->orWhereRaw("search({$column}) like search(?) escape '\\'", [$pattern]);
        }

        return $this;
    }

    /**
     * The enum of the relations a client may ask for with `with[]=roles`: a builder names its own, and its
     * FormRequest validates against the same one. Without it, no relation can be asked for.
     *
     * @return class-string<BackedEnum>|null
     */
    protected function relationEnum(): ?string
    {
        return null;
    }

    /**
     * The columns a client may sort by with `sort[name]=asc&sort[created_at]=desc`: the name it uses
     * => the column, or a `fn (Builder $query, string $direction)` for anything more than a column.
     *
     * @return array<string, string|Closure>
     */
    protected function sortable(): array
    {
        return [];
    }

    /**
     * The order when the request asks for none that is known.
     */
    protected function defaultOrder(): void {}

    /**
     * Eager loads the relations the client asked for. Only the cases of {@see relationEnum()} count: the
     * FormRequest rejects the others, and this keeps them out when the builder is used without one.
     *
     * @param  array<int, mixed>  $relations  `['roles', 'type']`.
     */
    public function loadRelations(array $relations): static
    {
        $enum = $this->relationEnum();

        $valid = $enum === null ? [] : collect($relations)
            ->map(fn ($relation) => is_string($relation) ? $enum::tryFrom($relation)?->value : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->with($valid);
    }

    /**
     * The names {@see sort()} accepts.
     *
     * @return array<int, string>
     */
    public function sortableNames(): array
    {
        return array_keys($this->sortable());
    }

    /**
     * Orders by the `sort` map of the request (`sort[name]=asc&sort[created_at]=desc`). Unknown names are
     * ignored, a direction other than `desc` means `asc`, and without a known one the builder's default order
     * applies. Ties are broken by key so pages stay stable.
     */
    public function sort(HttpRequest $request): static
    {
        $applied = false;

        foreach ((array) $request->input('sort', []) as $name => $value) {
            $rule = $this->sortable()[$name] ?? null;

            if ($rule === null) {
                continue;
            }

            $direction = SortDirection::tryFrom(strtolower((string) $value))?->value ?? SortDirection::Asc->value;
            $rule instanceof Closure ? $rule($this, $direction) : $this->orderBy($rule, $direction);
            $applied = true;
        }

        if (! $applied) {
            $this->defaultOrder();
        }

        return $this->orderBy($this->getModel()->getQualifiedKeyName());
    }
}
