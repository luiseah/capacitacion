<?php

namespace App\Requests\Strategies;

use App\Models\WorkPermitRequest;

class WorkPermitStrategy extends AbstractRequestStrategy
{
    public function requestModel(): string
    {
        return WorkPermitRequest::class;
    }
}
