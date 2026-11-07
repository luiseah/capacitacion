<?php

namespace Module\AcademicMobility\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Module\AcademicMobility\Core\Models\Mobility;

/**
 * Deny by default: each method defers to the host's ACL through the ability
 * names of {@see MobilityAbility}. Works with any user model that is
 * Authorizable (it never touches host classes).
 */
class MobilityPolicy
{
    public function viewAny(Authorizable $user): Response
    {
        return $this->check($user, MobilityAbility::ViewAny, __('You are not allowed to view mobilities.'));
    }

    public function view(Authorizable $user, Mobility $mobility): Response
    {
        return $this->check($user, MobilityAbility::View, __('You are not allowed to view this mobility.'));
    }

    public function create(Authorizable $user): Response
    {
        return $this->check($user, MobilityAbility::Create, __('You are not allowed to create mobilities.'));
    }

    private function check(Authorizable $user, MobilityAbility $ability, string $message): Response
    {
        return $user->can($ability->value) ? Response::allow() : Response::deny($message);
    }
}
