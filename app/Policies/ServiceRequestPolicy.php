<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ServiceRequest;
use App\Models\User;

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

    public function create(User $user): bool
    {
        return $user->can(PermissionName::CreateRequests->value);
    }
}
