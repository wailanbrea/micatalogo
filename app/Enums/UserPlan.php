<?php

namespace App\Enums;

enum UserPlan: string
{
    case Free = 'free';
    case Premium = 'premium';
    case Pro = 'pro';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratis',
            self::Premium => 'Básico',
            self::Pro => 'Pro',
            self::Custom => 'Personalizado',
        };
    }
}
