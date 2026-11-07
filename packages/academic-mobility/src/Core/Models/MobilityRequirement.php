<?php

namespace Module\AcademicMobility\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Module\AcademicMobility\Core\Concerns\HasTranslatedTexts;

/**
 * A requirement of one mobility: a copy of the catalog entry taken when the mobility was created.
 *
 * @property string $id
 * @property string $mobility_id
 * @property string|null $requirement_id
 * @property bool $is_mandatory
 * @property int $position
 * @property Carbon|null $fulfilled_at
 */
class MobilityRequirement extends Model
{
    use HasTranslatedTexts, HasUlids;

    protected $fillable = [
        'mobility_id', 'requirement_id', 'name', 'description', 'is_mandatory', 'position', 'translations', 'fulfilled_at',
    ];

    public function getTable(): string
    {
        return config('academic-mobility.table_prefix', 'academic_').'mobility_requirements';
    }

    public function getConnectionName(): ?string
    {
        return config('academic-mobility.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'position' => 'integer',
            'translations' => 'array',
            'fulfilled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Mobility, $this> */
    public function mobility(): BelongsTo
    {
        return $this->belongsTo(config('academic-mobility.models.mobility', Mobility::class), 'mobility_id');
    }
}
