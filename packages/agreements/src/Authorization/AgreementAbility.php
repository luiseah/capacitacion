<?php

namespace Module\Agreements\Authorization;

/**
 * Abilities the package checks through the Laravel Gate. Like the ones of the mobility package,
 * nothing is allowed until the host grants them (Spatie permissions with these names, `Gate::define()`...).
 */
enum AgreementAbility: string
{
    /** See the agreements (convenios) of the institutions. */
    case ViewAny = 'agreements.index';

    case Create = 'agreements.create';

    case Update = 'agreements.update';

    case Delete = 'agreements.destroy';
}
