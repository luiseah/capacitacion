<?php

namespace App\Enums;

use App\Requests\Strategies\BookReservationStrategy;
use App\Requests\Strategies\GradeChangeStrategy;
use App\Requests\Strategies\PurchaseStrategy;
use App\Requests\Strategies\RequestStrategy;
use App\Requests\Strategies\WorkPermitStrategy;

enum RequestTypeKey: string
{
    case BookReservation = 'book_reservation';
    case Purchase = 'purchase';
    case GradeChange = 'grade_change';
    case WorkPermit = 'work_permit';

    public function label(): string
    {
        return match ($this) {
            self::BookReservation => 'Book reservation',
            self::Purchase => 'Purchase request',
            self::GradeChange => 'Grade change request',
            self::WorkPermit => 'Work permit request',
        };
    }

    public function strategy(): RequestStrategy
    {
        return match ($this) {
            self::BookReservation => app(BookReservationStrategy::class),
            self::Purchase => app(PurchaseStrategy::class),
            self::GradeChange => app(GradeChangeStrategy::class),
            self::WorkPermit => app(WorkPermitStrategy::class),
        };
    }
}
