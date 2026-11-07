<?php

namespace App\Models\Builders;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\SearchInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Query builder of {@see User}: named, reusable constraints for searching users.
 *
 * @extends Builder<User>
 */
class UserBuilder extends Builder
{
    /**
     * Apply the list filters present in the request: `search` (name or email)
     * and `role`. Missing or blank filters are ignored.
     *
     * @param  Request  $request
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

            $this->where(function (Builder $q) use ($term) {
                $q->whereRaw("cast(id as text) like ? escape '\\'", [$term])
                    ->orWhereRaw("search(name) like search(?) escape '\\'", [$term])
                    ->orWhereRaw("search(lastname) like search(?) escape '\\'", [$term])
                    ->orWhereRaw("replace(phone, ' ', '') like replace(?, ' ', '') escape '\\'", [$term])
                    ->orWhereRaw("search(email) like search(?) escape '\\'", [$term]);
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

    /**
     * Eager load what the list representations need.
     */
    public function withListRelations(): static
    {
        return $this->with('roles');
    }

    public function alphabetical(): static
    {
        return $this->orderBy('name')->orderBy('lastname')->orderBy('id');
    }

    /**
     * Order by the `sort` map of the request (`sort[name]=asc&sort[created_at]=desc`).
     * Unknown columns are ignored, directions other than `desc` mean `asc`, and
     * it defaults to name ascending. Ties are broken by id so pages stay stable.
     *
     * @param  Request  $request
     * @return $this
     */
    public function sort(Request $request): self
    {
        $sort = $request->input('sort');
        $orders = is_array($sort) && $sort !== [] ? $sort : ['name' => 'asc'];

        foreach ($orders as $name => $value) {
            $direction = strtolower((string) $value) === 'desc' ? 'desc' : 'asc';

            match ($name) {
                'name' => $this->orderBy(DB::raw('lower(name)'), $direction),
                'lastname' => $this->orderBy(DB::raw('lower(lastname)'), $direction),
                'email' => $this->orderBy('email', $direction),
                // Verified vs unverified; `desc` lists verified users first.
                'verified' => $this->orderByRaw('(email_verified_at is null) '.($direction === 'desc' ? 'asc' : 'desc')),
                'phone' => $this->orderBy('phone', $direction),
                'created_at' => $this->orderBy('created_at', $direction),
                default => $this,
            };
        }

        return $this->orderBy('id');
    }
}
