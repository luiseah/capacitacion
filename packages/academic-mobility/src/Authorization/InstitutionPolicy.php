<?php

namespace Module\AcademicMobility\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Deny by default; see {@see MobilityPolicy}. The local institution cannot be
 * deleted: demote it first by making another one local.
 */
class InstitutionPolicy
{
    public function viewAny(Authorizable $user): Response
    {
        return $this->check($user, MobilityAbility::ViewAnyInstitutions, __('You are not allowed to view institutions.'));
    }

    public function create(Authorizable $user): Response
    {
        return $this->check($user, MobilityAbility::CreateInstitutions, __('You are not allowed to create institutions.'));
    }

    public function update(Authorizable $user, Institution $institution): Response
    {
        return $this->check($user, MobilityAbility::UpdateInstitutions, __('You are not allowed to edit this institution.'));
    }

    public function delete(Authorizable $user, Institution $institution): Response
    {
        if (! $user->can(MobilityAbility::DeleteInstitutions->value)) {
            return Response::deny(__('You are not allowed to delete this institution.'));
        }

        return $institution->is_local
            ? Response::deny(__('The local institution cannot be deleted.'))
            : Response::allow();
    }

    private function check(Authorizable $user, MobilityAbility $ability, string $message): Response
    {
        return $user->can($ability->value) ? Response::allow() : Response::deny($message);
    }
}
