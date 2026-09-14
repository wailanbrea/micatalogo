<?php

namespace App\Enums;

enum ProductModerationStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case PendingReview = 'pending_review';
    case Suspended = 'suspended';
}
