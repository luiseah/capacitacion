<?php

namespace Module\AcademicMobility\Contracts;

use Module\AcademicMobility\Core\Exceptions\AgreementNotApplicable;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Says whether an agreement can back a mobility. The package does not manage agreements: a mobility only
 * keeps the id of the one it is made under, and whoever does manage them (the `module/agreements` package,
 * or the host) binds this contract. Without one, a mobility cannot be made under an agreement.
 */
interface AgreementValidator
{
    /**
     * @param  Institution  $partner  The institution on the other side of the mobility.
     *
     * @throws AgreementNotApplicable
     */
    public function validate(string $agreementId, Institution $partner): void;
}
