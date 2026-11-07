<?php

namespace Module\Agreements\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Module\Agreements\Enums\AgreementActivity;
use Module\Agreements\Enums\AgreementParties;
use Module\Agreements\Enums\AgreementRenewal;
use Module\AcademicMobility\Core\Enums\MobilityScope;
use Module\AcademicMobility\Core\Models\Institution;
use Module\Agreements\Enums\AgreementStatus;
use Module\Agreements\Enums\AgreementType;
use Module\Agreements\Exceptions\InvalidAgreement;
use Module\Agreements\Models\Builders\AgreementBuilder;

/**
 * An agreement (convenio) between a partner institution and the local one. An institution can have
 * many, each with its own validity and the activities it covers: research, mobility, management or other.
 *
 * @property string $id
 * @property string $institution_id
 * @property AgreementType $type
 * @property AgreementParties $parties
 * @property AgreementStatus $status Where it was recorded to stand; see {@see statusOn()}.
 * @property string|null $purpose
 * @property Carbon|null $signed_on
 * @property AgreementRenewal $renewal
 * @property string|null $document_path
 * @property string|null $document_name
 * @property string|null $framework_id
 * @property string $name
 * @property string|null $reference
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property \Illuminate\Support\Collection<int, AgreementActivity> $activities
 * @property string|null $other_activity
 * @property string|null $notes
 * @property-read Institution $institution
 *
 * @method static AgreementBuilder query()
 */
class Agreement extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['institution_id', 'type', 'parties', 'status', 'framework_id', 'name', 'reference', 'purpose', 'signed_on', 'renewal', 'document_path', 'document_name', 'starts_on', 'ends_on', 'activities', 'other_activity', 'notes'];

    /** Specific unless it says it is a framework agreement. */
    protected $attributes = ['type' => 'specific', 'parties' => 'bilateral', 'status' => 'active', 'renewal' => 'manual'];

    protected static function booted(): void
    {
        // The rules of an agreement hold however it is saved.
        static::saving(function (self $agreement) {
            if ($agreement->ends_on !== null && $agreement->ends_on->lt($agreement->starts_on)) {
                throw InvalidAgreement::endsBeforeItStarts();
            }

            if (($agreement->activities ?? collect())->isEmpty()) {
                throw InvalidAgreement::withoutActivities();
            }

            if ($agreement->framework_id !== null) {
                if ($agreement->type === AgreementType::Framework) {
                    throw InvalidAgreement::frameworkWithAFramework();
                }

                if (! self::query()->whereKey($agreement->framework_id)->where('type', AgreementType::Framework->value)
                    ->where('institution_id', $agreement->institution_id)->exists()) {
                    throw InvalidAgreement::notAFrameworkOfTheInstitution();
                }
            }

            if ($agreement->isDirty('institution_id')
                && config('academic-mobility.models.institution', Institution::class)::query()->whereKey($agreement->institution_id)->where('is_local', true)->exists()) {
                throw InvalidAgreement::withTheLocalInstitution();
            }
        });
    }

    public function getTable(): string
    {
        return config('academic-mobility.table_prefix', 'academic_').'agreements';
    }

    public function getConnectionName(): ?string
    {
        return config('academic-mobility.connection') ?? parent::getConnectionName();
    }

    protected function casts(): array
    {
        return [
            'type' => AgreementType::class,
            'parties' => AgreementParties::class,
            'status' => AgreementStatus::class,
            'renewal' => AgreementRenewal::class,
            'signed_on' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'activities' => AsEnumCollection::of(AgreementActivity::class),
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): AgreementBuilder
    {
        return new AgreementBuilder($query);
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(config('academic-mobility.models.institution', Institution::class));
    }

    /**
     * The framework agreement a specific one develops, if it names one.
     *
     * @return BelongsTo<Agreement, $this>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(self::class, 'framework_id');
    }

    /**
     * The specific agreements that develop this framework one.
     *
     * @return HasMany<Agreement, $this>
     */
    public function specifics(): HasMany
    {
        return $this->hasMany(self::class, 'framework_id')->latestFirst();
    }

    /**
     * The other institutions of a multilateral agreement (`institution` is the main one).
     *
     * @return BelongsToMany<Institution, $this>
     */
    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(
            config('academic-mobility.models.institution', Institution::class),
            config('academic-mobility.table_prefix', 'academic_').'agreement_institution',
            'agreement_id',
            'institution_id',
        )->withTimestamps()->orderBy('name');
    }

    public function isMultilateral(): bool
    {
        return $this->parties === AgreementParties::Multilateral;
    }

    /**
     * Every partner institution of the agreement: the main one and, if multilateral, the others.
     *
     * @return Collection<int, Institution>
     */
    public function partners(): Collection
    {
        return $this->institution->newCollection([$this->institution, ...$this->institutions->all()]);
    }

    /**
     * Sets the other institutions of the agreement: none when it is bilateral, at least one when it is
     * multilateral, always partner institutions different from the main one.
     *
     * @param  array<int, string>  $ids
     */
    public function syncInstitutions(array $ids): static
    {
        $ids = array_values(array_unique($ids));

        if (! $this->isMultilateral()) {
            if ($ids !== []) {
                throw InvalidAgreement::bilateralWithSeveralInstitutions();
            }
        } elseif ($ids === []) {
            throw InvalidAgreement::multilateralNeedsMoreInstitutions();
        }

        $partners = $ids === [] ? 0 : config('academic-mobility.models.institution', Institution::class)::query()
            ->whereKey($ids)->where('is_local', false)->where('id', '!=', $this->institution_id)->count();

        if ($partners !== count($ids)) {
            throw InvalidAgreement::invalidInstitutionsOfAMultilateral();
        }

        $this->institutions()->sync($ids);
        $this->unsetRelation('institutions');

        return $this;
    }

    public function isFramework(): bool
    {
        return $this->type === AgreementType::Framework;
    }

    /**
     * Where the agreement stands on the date (today by default): as recorded, except that one recorded as in
     * force whose end date has passed is expired.
     */
    public function statusOn(?CarbonInterface $date = null): AgreementStatus
    {
        $day = ($date ?? now())->copy()->startOfDay();

        return $this->status === AgreementStatus::Active && $this->ends_on !== null && $this->ends_on->lt($day)
            ? AgreementStatus::Expired
            : $this->status;
    }

    /**
     * Whether it can be used on the date (today by default): in force, started and not ended.
     */
    public function isInForce(?CarbonInterface $date = null): bool
    {
        $day = ($date ?? now())->copy()->startOfDay();

        return $this->statusOn($day) === AgreementStatus::Active && $this->starts_on->lte($day);
    }

    /**
     * National when every institution of the agreement is in the local country, international when any is
     * somewhere else: it follows from the institutions, it is not chosen.
     *
     * @param  string|null  $localCountry  Country of the local institution (found when not given).
     */
    public function scope(?string $localCountry = null): MobilityScope
    {
        $localCountry ??= config('academic-mobility.models.institution', Institution::class)::local()?->country;

        return $this->partners()->every(fn (Institution $institution) => $institution->country === $localCountry)
            ? MobilityScope::National
            : MobilityScope::International;
    }

    public function hasDocument(): bool
    {
        return $this->document_path !== null;
    }

    public function covers(AgreementActivity $activity): bool
    {
        return $this->activities->contains($activity);
    }
}
