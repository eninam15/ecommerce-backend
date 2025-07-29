<?php

namespace App\Enums;

enum CouponStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case EXPIRED = 'expired';
    case EXHAUSTED = 'exhausted';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Activo',
            self::INACTIVE => 'Inactivo',
            self::EXPIRED => 'Expirado',
            self::EXHAUSTED => 'Agotado'
        };
    }
}