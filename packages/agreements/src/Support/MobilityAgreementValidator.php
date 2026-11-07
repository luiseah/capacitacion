<?php

namespace Module\Agreements\Support;

use Module\AcademicMobility\Contracts\AgreementValidator;
use Module\AcademicMobility\Core\Exceptions\AgreementNotApplicable;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Says whether an agreement can back a mobility: it exists, binds the institution on the other
 * side (its own agreements, or a multilateral one it takes part in) and is in force.
 */
class MobilityAgreementValidator implements AgreementValidator
{
    public function validate(string $agreementId, Institution $partner): void
    {
        $model = config('agreements.models.agreement');

        $agreement = $model::query()->find($agreementId) ?? throw AgreementNotApplicable::notFound();

        if (! $model::query()->whereKey($agreementId)->forInstitution($partner->getKey())->exists()) {
            throw AgreementNotApplicable::notOfTheInstitution();
        }

        if (! $agreement->isInForce()) {
            throw AgreementNotApplicable::notInForce();
        }
    }
}
