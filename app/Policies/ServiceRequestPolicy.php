<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RequestStatus;
use App\Enums\RequestTypeKey;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CreateRequests->value)
            || $user->can(PermissionName::ViewAnyRequests->value);
    }

    public function view(User $user, ServiceRequest $request): bool
    {
        return $request->requester_id === $user->id
            || $user->can(PermissionName::ViewAnyRequests->value);
    }

    /**
     * Whether the user may create requests, optionally of a specific type.
     * Inactive types are reported as not found.
     */
    public function create(User $user, ?RequestTypeKey $type = null): Response|bool
    {
        if (! $user->can(PermissionName::CreateRequests->value)) {
            return false;
        }

        if ($type !== null && ! RequestType::query()->where('key', $type->value)->where('is_active', true)->exists()) {
            return Response::denyAsNotFound();
        }

        return true;
    }

    /**
     * Requesters may edit their own requests while they are still drafts;
     * users who can manage requests may edit them in any state.
     */
    public function update(User $user, ServiceRequest $request): bool
    {
        if ($user->can(PermissionName::ManageRequests->value)) {
            return true;
        }

        return $request->requester_id === $user->id
            && $request->status === RequestStatus::Draft;
    }

    /**
     * Same rule as editing: owners can delete their drafts, managers any request.
     */
    public function delete(User $user, ServiceRequest $request): bool
    {
        return $this->update($user, $request);
    }
}
