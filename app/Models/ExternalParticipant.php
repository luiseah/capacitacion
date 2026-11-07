<?php

namespace App\Models;

use Database\Factories\ExternalParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * A visitor of an incoming mobility: a student, professor or staff member of another institution.
 *
 * @property int $id
 * @property string $institution_id
 * @property ParticipantKind $kind
 * @property string $name
 * @property string $lastname
 * @property string|null $email
 * @property string|null $phone
 * @property string $document_number
 * @property string|null $code
 * @property string $affiliation Program (student), faculty or department (professor) or dependency (staff).
 * @property-read string $full_name
 * @property-read Institution $institution
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['institution_id', 'kind', 'name', 'lastname', 'email', 'phone', 'document_number', 'code', 'affiliation'])]
class ExternalParticipant extends Model
{
    /** @use HasFactory<ExternalParticipantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ParticipantKind::class];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->name} {$this->lastname}"));
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(config('academic-mobility.models.institution', Institution::class));
    }
}
