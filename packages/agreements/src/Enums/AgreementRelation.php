<?php

namespace Module\Agreements\Enums;

/**
 * The relations of an agreement the list can include (`with[]=institution`): validated by
 * `IndexAgreementsRequest` and loaded by `AgreementBuilder`.
 */
enum AgreementRelation: string
{
    case Institution = 'institution';
    case Institutions = 'institutions';
    case Framework = 'framework';
}
