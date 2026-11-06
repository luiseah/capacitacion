<?php

namespace App\Requests\Strategies;

use App\Http\Requests\Requests\GradeChangeFormRequest;
use App\Models\GradeChangeRequest;

class GradeChangeStrategy extends AbstractRequestStrategy
{
    public function requestModel(): string
    {
        return GradeChangeRequest::class;
    }

    public function formRequest(): string
    {
        return GradeChangeFormRequest::class;
    }
}
