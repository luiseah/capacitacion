<?php

namespace App\Requests\Strategies;

use App\Models\GradeChangeRequest;

class GradeChangeStrategy extends AbstractRequestStrategy
{
    public function requestModel(): string
    {
        return GradeChangeRequest::class;
    }
}
