<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): Response|bool
    {
        return $user->can(PermissionName::IndexUsers->value)
            ? Response::allow()
            : Response::deny(__('You are not allowed to view users.'));
    }

    public function view(User $user, User $target): Response|bool
    {
        return $user->can(PermissionName::ShowUsers->value)
            ? Response::allow()
            : Response::deny(__('You are not allowed to view this user.'));
    }

    public function update(User $user, User $target): Response|bool
    {
        return $user->can(PermissionName::UpdateUsers->value)
            ? Response::allow()
            : Response::deny(__('You are not allowed to edit this user.'));
    }

    public function delete(User $user, User $target): Response|bool
    {
        if (! $user->can(PermissionName::DestroyUsers->value)) {
            return Response::deny(__('You are not allowed to delete this user.'));
        }

        return $user->is($target)
            ? Response::deny(__('You cannot delete your own account here.'))
            : Response::allow();
    }
}
