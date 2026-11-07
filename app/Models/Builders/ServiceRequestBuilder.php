<?php

namespace App\Models\Builders;

use App\Enums\PermissionName;
use App\Enums\RequestTypeKey;
use App\Enums\ServiceRequestRelation;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Module\Pagination\PaginatedBuilder;

/**
 * Query builder of {@see ServiceRequest}: named, reusable constraints so
 * controllers read as intent instead of repeating `where` clauses.
 *
 * @extends PaginatedBuilder<ServiceRequest>
 */
class ServiceRequestBuilder extends PaginatedBuilder
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
     * The list options of the request: `with` (relations, see {@see relationEnum()}) and `sort`.
     */
    public function applyFilters(Request $request): static
    {
        $this->loadRelations($request->array('with'));

        return $this->sort($request);
    }

    protected function relationEnum(): ?string
    {
        return ServiceRequestRelation::class;
    }

    protected function sortable(): array
    {
        return ['created_at' => 'created_at', 'status' => 'status', 'priority' => 'priority'];
    }

    protected function defaultOrder(): void
    {
        $this->latest();
    }

    public function newest(): static
    {
        return $this->latest();
    }
}
