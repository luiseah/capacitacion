<?php

namespace Module\AcademicMobility\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Module\AcademicMobility\Core\Concerns\HasTranslatedTexts;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Builders\RequirementBuilder;

/**
 * Something a mobility has to bring (a document, an authorization...). It applies to the
 * mobilities that match every criterion it names; an empty criterion matches anything.
 *
 * @property string $id
 * @property MobilityDirection|null $direction
 * @property MobilityScope|null $scope
 * @property ParticipantKind|null $participant_kind
 * @property bool $is_mandatory
 * @property bool $is_active
 * @property int $position
 *
 * @method static RequirementBuilder query()
 */
class Requirement extends Model
{
    use HasTranslatedTexts, HasUlids, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'direction', 'scope', 'participant_kind',
        'is_mandatory', 'is_active', 'position', 'translations',
    ];

    public function getTable(): string
    {
        return config('academic-mobility.table_prefix', 'academic_').'requirements';
    }

    public function getConnectionName(): ?string
    {
        return config('academic-mobility.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return [
            'direction' => MobilityDirection::class,
            'scope' => MobilityScope::class,
            'participant_kind' => ParticipantKind::class,
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
            'translations' => 'array',
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): RequirementBuilder
    {
        return new RequirementBuilder($query);
    }
}
