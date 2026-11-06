<?php

namespace App\Requests\Strategies;

use App\Http\Requests\Requests\WorkPermitFormRequest;
use App\Models\WorkPermitRequest;

class WorkPermitStrategy extends AbstractRequestStrategy
{
    public function requestModel(): string
    {
        return WorkPermitRequest::class;
    }

    public function formRequest(): string
    {
        return WorkPermitFormRequest::class;
    }
}
