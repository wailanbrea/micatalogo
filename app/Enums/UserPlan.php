<?php

namespace App\Enums;

enum UserPlan: string
{
    case Free = 'free';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratis',
            self::Premium => 'Premium',
        };
    }
}
