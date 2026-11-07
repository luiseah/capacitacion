<?php

namespace Module\AcademicMobility\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Module\AcademicMobility\Core\Enums\MobilityDirection;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Enums\MobilityStatus;
use Module\AcademicMobility\Core\Models\Builders\MobilityBuilder;
use Module\AcademicMobility\Core\ValueObjects\ParticipantReference;

/**
 * @property string $id
 * @property string $participant_type
 * @property string $participant_id
 * @property string $strategy
 * @property MobilityStatus $status
 * @property MobilityDirection|null $direction
 * @property MobilityScope|null $scope
 * @property string|null $origin_institution_id
 * @property string|null $destination_institution_id
 * @property string|null $agreement_id Only there when the `module/agreements` package (or the host) adds the column.
 * @property array<string, mixed>|null $metadata
 *
 * @method static MobilityBuilder query()
 */
class Mobility extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'participant_type', 'participant_id', 'strategy', 'status', 'metadata',
        'direction', 'scope', 'origin_institution_id', 'destination_institution_id', 'agreement_id',
    ];

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): MobilityBuilder
    {
        return new MobilityBuilder($query);
    }

    public function getTable(): string
    {
        return config('academic-mobility.table_prefix', 'academic_').'mobilities';
    }

    public function getConnectionName(): ?string
    {
        return config('academic-mobility.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return [
            'status' => MobilityStatus::class,
            'direction' => MobilityDirection::class,
            'scope' => MobilityScope::class,
            'metadata' => 'array',
        ];
    }

    public function participant(): ParticipantReference
    {
        return new ParticipantReference($this->participant_type, $this->participant_id);
    }

    /** @return BelongsTo<Institution, $this> */
    public function originInstitution(): BelongsTo
    {
        return $this->belongsTo(config('academic-mobility.models.institution', Institution::class), 'origin_institution_id');
    }

    /** @return BelongsTo<Institution, $this> */
    public function destinationInstitution(): BelongsTo
    {
        return $this->belongsTo(config('academic-mobility.models.institution', Institution::class), 'destination_institution_id');
    }

    /**
     * What the mobility had to bring, as copied when it was created.
     *
     * @return HasMany<MobilityRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(MobilityRequirement::class, 'mobility_id')->orderBy('position')->orderBy('name');
    }
}
