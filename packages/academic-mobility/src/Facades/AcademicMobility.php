<?php

namespace Module\AcademicMobility\Facades;

use Illuminate\Support\Facades\Facade;
use Module\AcademicMobility\AcademicMobility as Manager;

/**
 * @method static Manager registerRule(string $alias, string|object $rule)
 * @method static Manager registerStrategy(string $alias, string|object $strategy)
 * @method static Manager registerConditionEvaluator(string $alias, string|object $evaluator)
 * @method static Manager registerParticipantResolver(string $type, string|object $resolver, ?\Module\AcademicMobility\Core\Enums\MobilityDirection $direction = null)
 * @method static \Module\AcademicMobility\Contracts\ParticipantResolver participantResolver(\Module\AcademicMobility\Core\Enums\MobilityDirection $direction, string $kind)
 * @method static array participantKinds(\Module\AcademicMobility\Core\Enums\MobilityDirection $direction)
 * @method static Manager registerDocumentProvider(string $alias, string|object $provider)
 * @method static Manager registerApprover(string $alias, string|object $approver)
 * @method static Manager resolveCurrentParticipantUsing(\Closure $resolver)
 * @method static Manager preventInstitutionDeletionUsing(\Closure $guard)
 * @method static string|null institutionDeletionBlockedBy(\Module\AcademicMobility\Core\Models\Institution $institution)
 * @method static array currentParticipants(?\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static bool has(\Module\AcademicMobility\Support\ExtensionKind $kind, string $alias)
 * @method static array aliases(\Module\AcademicMobility\Support\ExtensionKind $kind)
 * @method static object extension(\Module\AcademicMobility\Support\ExtensionKind $kind, string $alias)
 * @method static \Module\AcademicMobility\Core\Models\Mobility create(\Module\AcademicMobility\Core\DTOs\CreateMobilityData $data)
 * @method static \Module\AcademicMobility\Core\Models\Mobility transition(\Module\AcademicMobility\Core\Models\Mobility $mobility, \Module\AcademicMobility\Core\Enums\MobilityStatus $to)
 *
 * @see Manager
 */
class AcademicMobility extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
