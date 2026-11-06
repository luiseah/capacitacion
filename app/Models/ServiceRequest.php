<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RequestTypeKey;
use App\Models\Builders\ServiceRequestBuilder;
use App\Models\Scopes\RequestTypeScope;
use Database\Factories\ServiceRequestFactory;
use App\Requests\Field;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Base model for every request. Each concrete type extends it, shares the
 * `requests` table (single-table inheritance keyed by `request_type_id`) and
 * declares the fields shown in the UI; validation lives in the type's form request.
 *
 * Not named `Request` to avoid clashing with Illuminate\Http\Request.
 */
#[ScopedBy([RequestTypeScope::class])]
#[UseFactory(ServiceRequestFactory::class)]
#[UseEloquentBuilder(ServiceRequestBuilder::class)]
class ServiceRequest extends Model implements AuditableContract
{
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'requests';

    protected $fillable = [
        'request_type_id', 'requester_id', 'assignee_id', 'title', 'description',
        'status', 'priority', 'metadata', 'submitted_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'priority' => RequestPriority::class,
            'metadata' => 'array',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * The request type this model represents. Concrete models must override it.
     */
    public static function typeKey(): RequestTypeKey
    {
        throw new \LogicException(static::class.' must define its request type key.');
    }

    /**
     * Field definitions of this request type. Concrete models must override it.
     *
     * @return array<int, Field>
     */
    public static function fields(): array
    {
        return [];
    }

    protected static function booted(): void
    {
        // Concrete request models stamp their own type on creation.
        static::creating(function (self $request) {
            if ($request::class !== self::class) {
                $request->request_type_id ??= RequestType::query()
                    ->where('key', $request::typeKey()->value)
                    ->value('id');
            }
        });
    }

    /**
     * The primary key stays an auto-incrementing id; only `uuid` is generated.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
