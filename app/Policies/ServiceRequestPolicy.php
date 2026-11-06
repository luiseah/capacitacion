<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RequestStatus;
use App\Enums\RequestTypeKey;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Owners can always see their own requests and edit or delete them while they
 * are drafts. The permissions extend that to every request.
 */
class ServiceRequestPolicy
{
    /**
     * Everyone can list; `ServiceRequestBuilder::visibleTo()` narrows the list
     * to the user's own requests unless they have `requests.index`.
     */
    public function viewAny(User $user): Response|bool
    {
        return Response::allow();
    }

    public function view(User $user, ServiceRequest $request): Response|bool
    {
        return $this->isOwner($user, $request) || $user->can(PermissionName::ShowRequests->value)
            ? Response::allow()
            : Response::deny(__('You are not allowed to view this request.'));
    }

    /**
     * Whether the user may create requests, optionally of a specific type.
     * Inactive types are reported as not found.
     */
    public function create(User $user, ?RequestTypeKey $type = null): Response|bool
    {
        if (! $user->can(PermissionName::CreateRequests->value)) {
            return Response::deny(__('You are not allowed to create requests.'));
        }

        if ($type !== null && ! RequestType::query()->where('key', $type->value)->where('is_active', true)->exists()) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    public function update(User $user, ServiceRequest $request): Response|bool
    {
        if ($user->can(PermissionName::UpdateRequests->value) || $this->isEditableDraftOf($user, $request)) {
            return Response::allow();
        }

        return Response::deny($this->isOwner($user, $request)
            ? __('Only draft requests can be edited.')
            : __('You are not allowed to edit this request.'));
    }

    public function delete(User $user, ServiceRequest $request): Response|bool
    {
        if ($user->can(PermissionName::DestroyRequests->value) || $this->isEditableDraftOf($user, $request)) {
            return Response::allow();
        }

        return Response::deny($this->isOwner($user, $request)
            ? __('Only draft requests can be deleted.')
            : __('You are not allowed to delete this request.'));
    }

    private function isOwner(User $user, ServiceRequest $request): bool
    {
        return $request->requester_id === $user->id;
    }

    private function isEditableDraftOf(User $user, ServiceRequest $request): bool
    {
        return $this->isOwner($user, $request) && $request->status === RequestStatus::Draft;
    }
}
