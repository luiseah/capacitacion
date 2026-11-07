<?php

namespace Module\Agreements\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Module\Agreements\Models\Agreement;

/**
 * Deny by default; see {@see \Module\AcademicMobility\Authorization\MobilityPolicy}. Agreements (convenios) are the cooperation agreements of the partner institutions with the local one.
 */
class AgreementPolicy
{
    public function viewAny(Authorizable $user): Response
    {
        return $this->check($user, AgreementAbility::ViewAny, __('You are not allowed to view agreements.'));
    }

    public function create(Authorizable $user): Response
    {
        return $this->check($user, AgreementAbility::Create, __('You are not allowed to create agreements.'));
    }

    public function update(Authorizable $user, Agreement $agreement): Response
    {
        return $this->check($user, AgreementAbility::Update, __('You are not allowed to edit this agreement.'));
    }

    public function delete(Authorizable $user, Agreement $agreement): Response
    {
        return $this->check($user, AgreementAbility::Delete, __('You are not allowed to delete this agreement.'));
    }

    private function check(Authorizable $user, AgreementAbility $ability, string $message): Response
    {
        return $user->can($ability->value) ? Response::allow() : Response::deny($message);
    }
}
