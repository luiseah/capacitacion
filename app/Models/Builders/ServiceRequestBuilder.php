<?php

namespace App\Models\Builders;

use App\Enums\PermissionName;
use App\Enums\RequestTypeKey;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query builder of {@see ServiceRequest}: named, reusable constraints so
 * controllers read as intent instead of repeating `where` clauses.
 *
 * @extends Builder<ServiceRequest>
 */
class ServiceRequestBuilder extends Builder
{
    /**
     * Only the requests the user may see: their own, unless they can index every request.
     */
    public function visibleTo(User $user): static
    {
        return $this->unless(
            $user->can(PermissionName::IndexRequests->value),
            fn (self $query) => $query->where('requester_id', $user->id),
        );
    }

    /**
     * Only the requests of the given type.
     */
    public function ofType(RequestTypeKey $type): static
    {
        return $this->whereIn(
            'request_type_id',
            RequestType::query()->select('id')->where('key', $type->value),
        );
    }

    /**
     * Eager load what the list representations need.
     */
    public function withListRelations(): static
    {
        return $this->with(['type', 'requester']);
    }

    public function newest(): static
    {
        return $this->latest();
    }
}
