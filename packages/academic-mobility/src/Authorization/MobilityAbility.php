<?php

namespace Module\AcademicMobility\Authorization;

/**
 * Abilities the package checks through the Laravel Gate. The package does not
 * know how they are granted: the host maps them to its own ACL (Spatie
 * permissions with these names, `Gate::define()`, a `Gate::before()` hook...).
 * Nothing is allowed until the host grants them.
 */
enum MobilityAbility: string
{
    /** See the list of mobilities. */
    case ViewAny = 'mobilities.index';

    /** View a single mobility. */
    case View = 'mobilities.show';

    /** Create a mobility (form, options and store endpoints). */
    case Create = 'mobilities.create';

    /** See the institutions list (and the options of the mobility form). */
    case ViewAnyInstitutions = 'institutions.index';

    case CreateInstitutions = 'institutions.create';

    /** Edit an institution, including making it the local one. */
    case UpdateInstitutions = 'institutions.update';

    case DeleteInstitutions = 'institutions.destroy';

    /** See the catalog of requirements. */
    case ViewAnyRequirements = 'requirements.index';

    case CreateRequirements = 'requirements.create';

    case UpdateRequirements = 'requirements.update';

    case DeleteRequirements = 'requirements.destroy';

    /** See the agreements (convenios) of the institutions. */
    case ViewAnyAgreements = 'agreements.index';

    case CreateAgreements = 'agreements.create';

    case UpdateAgreements = 'agreements.update';

    case DeleteAgreements = 'agreements.destroy';
}
