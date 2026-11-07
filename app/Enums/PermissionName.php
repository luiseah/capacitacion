<?php

namespace App\Enums;

/**
 * Permissions named after their routes. The requests ones apply to
 * every request; owners can always see their own requests and edit or delete
 * them while they are drafts, without needing any of these.
 */
enum PermissionName: string
{
    /** See the list with everyone's requests (without it: only your own). */
    case IndexRequests = 'requests.index';

    /** View any request. */
    case ShowRequests = 'requests.show';

    case CreateRequests = 'requests.create';

    /** Edit any request, in any state. */
    case UpdateRequests = 'requests.update';

    /** Delete any request, in any state. */
    case DestroyRequests = 'requests.destroy';

    /** See the users list. */
    case IndexUsers = 'users.index';

    /** View any user. */
    case ShowUsers = 'users.show';

    /** Edit any user and their roles. */
    case UpdateUsers = 'users.update';

    /** Delete any user (never yourself). */
    case DestroyUsers = 'users.destroy';

    /** See the academic mobilities list (package ability `MobilityAbility::ViewAny`). */
    case IndexMobilities = 'mobilities.index';

    /** View any academic mobility (package ability `MobilityAbility::View`). */
    case ShowMobilities = 'mobilities.show';

    /** Create academic mobilities (package ability `MobilityAbility::Create`). */
    case CreateMobilities = 'mobilities.create';

    /** See the institutions list (package ability `MobilityAbility::ViewAnyInstitutions`). */
    case IndexInstitutions = 'institutions.index';

    /** Create institutions. */
    case CreateInstitutions = 'institutions.create';

    /** Edit any institution, including making it the local one. */
    case UpdateInstitutions = 'institutions.update';

    /** Delete an unused institution (never the local one). */
    case DestroyInstitutions = 'institutions.destroy';

    /** See the catalog of mobility requirements (package ability `requirements.index`). */
    case IndexRequirements = 'requirements.index';

    /** Add requirements to the catalog. */
    case CreateRequirements = 'requirements.create';

    /** Edit any requirement of the catalog. */
    case UpdateRequirements = 'requirements.update';

    /** Remove a requirement from the catalog (existing requests keep their copy). */
    case DestroyRequirements = 'requirements.destroy';

    /** See the agreements (convenios) of the institutions (package ability `agreements.index`). */
    case IndexAgreements = 'agreements.index';

    case CreateAgreements = 'agreements.create';

    /** Edit any agreement. */
    case UpdateAgreements = 'agreements.update';

    case DestroyAgreements = 'agreements.destroy';
}
