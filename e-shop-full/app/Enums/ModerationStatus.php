<?php

namespace App\Enums;

enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case PendingReview = 'pending_review';
    case Rejected = 'rejected';
}
