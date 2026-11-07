<?php

namespace Module\Agreements\Enums;

/**
 * A framework agreement (marco) sets the general terms of the cooperation with an institution;
 * a specific one develops a concrete activity, and can depend on a framework one.
 */
enum AgreementType: string
{
    case Framework = 'framework';
    case Specific = 'specific';
}
