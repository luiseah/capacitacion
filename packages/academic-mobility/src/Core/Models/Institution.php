<?php

namespace Module\AcademicMobility\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Module\AcademicMobility\Core\Models\Builders\InstitutionBuilder;

/**
 * A university or institution a mobility starts or ends at. Exactly one is the
 * local one (`is_local`): the institution the application belongs to.
 *
 * @property string $id
 * @property string $name
 * @property string $country  ISO 3166-1 alpha-2, upper case
 * @property bool $is_local
 *
 * @method static InstitutionBuilder query()
 */
class Institution extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['name', 'country', 'is_local'];

    public function getTable(): string
    {
        return config('academic-mobility.table_prefix', 'academic_').'institutions';
    }

    public function getConnectionName(): ?string
    {
        return config('academic-mobility.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return ['is_local' => 'boolean'];
    }

    /**
     * The local institution, or null while none has been defined.
     */
    public static function local(): ?static
    {
        return static::query()->where('is_local', true)->first();
    }

    /**
     * Make this the local institution, demoting the previous one.
     */
    /**
     * The agreements of this institution with the local one: as many as it needs.
     *
     * @return HasMany<Agreement, $this>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(config('academic-mobility.models.agreement', Agreement::class))->latestFirst();
    }

    /**
     * Whether the institution takes part in any agreement: as the main one or in a multilateral one.
     */
    public function hasAgreements(): bool
    {
        return config('academic-mobility.models.agreement', Agreement::class)::query()->forInstitution($this->getKey())->exists();
    }

    public function makeLocal(): static
    {
        $this->getConnection()->transaction(function () {
            static::query()->where('is_local', true)->whereKeyNot($this->getKey())->update(['is_local' => false]);

            $this->forceFill(['is_local' => true])->save();
        });

        return $this;
    }

    public function setCountryAttribute(string $value): void
    {
        $this->attributes['country'] = strtoupper($value);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): InstitutionBuilder
    {
        return new InstitutionBuilder($query);
    }
}
