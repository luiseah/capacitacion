<?php

namespace App\Models;

use App\Enums\RequestTypeKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RequestType extends Model implements AuditableContract
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['key', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'key' => RequestTypeKey::class,
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }
}
