<?php

namespace App\Requests\Strategies;

use App\Http\Requests\Requests\BookReservationFormRequest;
use App\Models\BookReservationRequest;

class BookReservationStrategy extends AbstractRequestStrategy
{
    public function requestModel(): string
    {
        return BookReservationRequest::class;
    }

    public function formRequest(): string
    {
        return BookReservationFormRequest::class;
    }
}
