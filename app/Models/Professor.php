<?php

namespace App\Models;

use App\Enums\ContractType;
use Database\Factories\ProfessorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $employee_code
 * @property string $faculty
 * @property string $academic_rank
 * @property ContractType $contract_type
 * @property Carbon $hired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'employee_code', 'faculty', 'academic_rank', 'contract_type', 'hired_at'])]
class Professor extends Model
{
    /** @use HasFactory<ProfessorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['contract_type' => ContractType::class, 'hired_at' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
