<?php

namespace Module\AcademicMobility\Core\Support;

use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Exceptions\InstitutionNotFound;
use Module\AcademicMobility\Core\Exceptions\InvalidRoute;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Core\ValueObjects\MobilityRoute;

/**
 * The local institution is always one end of a mobility; the chosen direction says which:
 *
 *  - incoming: the other institution is the origin, the local one the destination;
 *  - outgoing: the local one is the origin, the other institution the destination.
 *
 * The chosen scope has to agree with the other institution: same country as the local one =>
 * national, otherwise international.
 */
class RouteResolver
{
    /**
     * @throws InstitutionNotFound
     * @throws InvalidRoute
     */
    public function resolve(MobilityDirection $direction, MobilityScope $scope, string $institutionId): MobilityRoute
    {
        $model = config('academic-mobility.models.institution', Institution::class);

        $local = $model::local() ?? throw InvalidRoute::noLocalInstitution();
        $other = $model::find($institutionId) ?? throw new InstitutionNotFound('institution_id', $institutionId);

        if ($other->is($local)) {
            throw InvalidRoute::counterpartIsLocal();
        }

        [$origin, $destination] = $direction === MobilityDirection::Incoming ? [$other, $local] : [$local, $other];

        $actual = $other->country === $local->country ? MobilityScope::National : MobilityScope::International;

        if ($actual !== $scope) {
            throw InvalidRoute::scopeMismatch($scope->value, $local->country);
        }

        return new MobilityRoute($origin, $destination, $direction, $scope);
    }
}
