<?php

namespace App\Requests\Strategies;

use App\Models\BookReservationRequest;

class BookReservationStrategy extends AbstractRequestStrategy
{
    public function requestModel(): string
    {
        return BookReservationRequest::class;
    }
}
