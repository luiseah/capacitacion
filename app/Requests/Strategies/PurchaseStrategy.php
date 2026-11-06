<?php

namespace App\Requests\Strategies;

use App\Models\PurchaseRequest;

class PurchaseStrategy extends AbstractRequestStrategy
{
    public function requestModel(): string
    {
        return PurchaseRequest::class;
    }
}
