<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open        = 'open';
    case UnderReview = 'under_review';
    case Resolved    = 'resolved';
    case Rejected    = 'rejected';
}
