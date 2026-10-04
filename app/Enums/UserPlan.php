<?php

namespace App\Enums;

enum UserPlan: string
{
    case Free = 'free';
    case Premium = 'premium';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratis',
            self::Premium => 'Premium',
            self::Pro => 'Pro',
        };
    }
}
