<?php

namespace App\Enums;

enum UserRole: string
{
    case Merchant = 'merchant';
    case Moderator = 'moderator';
    case SuperAdmin = 'super_admin';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Merchant => 'Marchand',
            self::Moderator => 'Modérateur',
            self::SuperAdmin => 'Super administrateur',
        };
    }
}
