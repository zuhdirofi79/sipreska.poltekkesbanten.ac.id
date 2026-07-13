<?php

namespace App\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case AdminUnit = 'admin_unit';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminUnit => 'Admin Unit',
        };
    }
}
