<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
