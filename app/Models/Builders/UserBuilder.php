<?php

namespace App\Models\Builders;

use App\Enums\RoleName;
use App\Enums\UserRelation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Module\Pagination\PaginatedBuilder;
use Module\Pagination\Search\SearchInput;

/**
 * Query builder of {@see User}: named, reusable constraints for searching users.
 *
 * @extends PaginatedBuilder<User>
 */
class UserBuilder extends PaginatedBuilder
{
    /**
     * Apply the list filters present in the request: `search` (name or email)
     * and `role`, then `with` (relations, see {@see relationEnum()}) and `sort`. Missing or blank filters are ignored.
     *
     * @return $this
     */
    public function applyFilters(Request $request): self
    {
        if ($request->has('search')) {
            $this->bySearch($request->string('search')->toString());
        }

        if ($request->has('role')) {
            $this->byRole($request->enum('role', RoleName::class));
        }

        $this->loadRelations($request->array('with'));
        $this->sort($request);

        return $this;
    }

    /**
     * Users matching every word of the text in their id, name, last name, email or phone,
     * ignoring case and accents (`search()` SQL function); the phone is
     * compared without spaces. A blank text
     * applies no filter.
     */
    public function bySearch(?string $text): static
    {
        foreach (SearchInput::tokens((string) $text) as $token) {
            $term = SearchInput::like($token);

            $this->where(function (Builder $q) use ($term, $token) {
                $q->whereRaw("cast(id as text) like ? escape '\\'", [$term])
                    ->orWhereSearch(['name', 'lastname', 'email'], $token)
                    ->orWhereRaw("replace(phone, ' ', '') like replace(?, ' ', '') escape '\\'", [$term]);
            });
        }

        return $this;
    }

    /**
     * Users that have the given role. A null role applies no filter.
     */
    public function byRole(?RoleName $role): static
    {
        return $this->when($role !== null, fn (self $query) => $query->role($role->value));
    }

    public function verified(): static
    {
        return $this->whereNotNull('email_verified_at');
    }

    protected function relationEnum(): ?string
    {
        return UserRelation::class;
    }

    protected function sortable(): array
    {
        return [
            'name' => fn ($query, string $direction) => $query->orderBy(DB::raw('lower(name)'), $direction),
            'lastname' => fn ($query, string $direction) => $query->orderBy(DB::raw('lower(lastname)'), $direction),
            'email' => 'email',
            'phone' => 'phone',
            'created_at' => 'created_at',
            // Verified vs unverified; `desc` lists verified users first.
            'verified' => fn ($query, string $direction) => $query->orderByRaw('(email_verified_at is null) '.($direction === 'desc' ? 'asc' : 'desc')),
        ];
    }

    protected function defaultOrder(): void
    {
        $this->orderBy(DB::raw('lower(name)'));
    }

    public function alphabetical(): static
    {
        return $this->orderBy('name')->orderBy('lastname')->orderBy('id');
    }
}
