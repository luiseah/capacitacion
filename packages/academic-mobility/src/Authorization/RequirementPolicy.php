<?php

namespace Module\AcademicMobility\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Module\AcademicMobility\Core\Models\Requirement;

/**
 * Deny by default; see {@see MobilityPolicy}. Requirements are the catalog of what each kind of mobility brings.
 */
class RequirementPolicy
{
    public function viewAny(Authorizable $user): Response
    {
        return $this->check($user, MobilityAbility::ViewAnyRequirements, __('You are not allowed to view requirements.'));
    }

    public function create(Authorizable $user): Response
    {
        return $this->check($user, MobilityAbility::CreateRequirements, __('You are not allowed to create requirements.'));
    }

    public function update(Authorizable $user, Requirement $requirement): Response
    {
        return $this->check($user, MobilityAbility::UpdateRequirements, __('You are not allowed to edit this requirement.'));
    }

    public function delete(Authorizable $user, Requirement $requirement): Response
    {
        return $this->check($user, MobilityAbility::DeleteRequirements, __('You are not allowed to delete this requirement.'));
    }

    private function check(Authorizable $user, MobilityAbility $ability, string $message): Response
    {
        return $user->can($ability->value) ? Response::allow() : Response::deny($message);
    }
}
