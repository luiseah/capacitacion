<?php

namespace App\Models;

use Database\Factories\AdministrativeStaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $employee_code
 * @property string $dependency
 * @property string $position
 * @property Carbon $hired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'employee_code', 'dependency', 'position', 'hired_at'])]
class AdministrativeStaff extends Model
{
    /** @use HasFactory<AdministrativeStaffFactory> */
    use HasFactory;

    protected $table = 'administrative_staff';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['hired_at' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
