<?php

namespace Module\AcademicMobility\Core\Enums;

/**
 * The relations of a mobility the list can include (`with[]=originInstitution`): validated by
 * `IndexMobilitiesRequest` and loaded by `MobilityBuilder`.
 */
enum MobilityRelation: string
{
    case OriginInstitution = 'originInstitution';
    case DestinationInstitution = 'destinationInstitution';
    case Requirements = 'requirements';
}
