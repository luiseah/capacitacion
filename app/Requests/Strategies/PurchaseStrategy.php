<?php

namespace App\Requests\Strategies;

use App\Http\Requests\Requests\PurchaseFormRequest;
use App\Models\PurchaseRequest;

class PurchaseStrategy extends AbstractRequestStrategy
{
    public function requestModel(): string
    {
        return PurchaseRequest::class;
    }

    public function formRequest(): string
    {
        return PurchaseFormRequest::class;
    }
}
