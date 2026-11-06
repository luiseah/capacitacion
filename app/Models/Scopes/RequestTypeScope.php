<?php

namespace App\Models\Scopes;

use App\Models\Builders\ServiceRequestBuilder;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits a concrete request model (e.g. PurchaseRequest) to the rows of its own
 * type. The base ServiceRequest model keeps seeing every request.
 */
class RequestTypeScope implements Scope
{
    /**
     * @param  ServiceRequestBuilder  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($model::class === ServiceRequest::class) {
            return;
        }

        /** @var ServiceRequest $model */
        $builder->ofType($model::typeKey());
    }
}
