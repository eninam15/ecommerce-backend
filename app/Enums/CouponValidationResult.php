<?php

namespace App\Enums;

enum CouponValidationResult: string
{
    case VALID = 'valid';
    case NOT_FOUND = 'not_found';
    case INACTIVE = 'inactive';
    case EXPIRED = 'expired';
    case NOT_STARTED = 'not_started';
    case USAGE_LIMIT_EXCEEDED = 'usage_limit_exceeded';
    case USER_LIMIT_EXCEEDED = 'user_limit_exceeded';
    case MINIMUM_AMOUNT_NOT_MET = 'minimum_amount_not_met';
    case NOT_FIRST_PURCHASE = 'not_first_purchase';
    case NO_APPLICABLE_PRODUCTS = 'no_applicable_products';
    case ALREADY_APPLIED = 'already_applied';

    public function message(): string
    {
        return match($this) {
            self::VALID => 'Cupón válido',
            self::NOT_FOUND => 'Cupón no encontrado',
            self::INACTIVE => 'Cupón inactivo',
            self::EXPIRED => 'Cupón expirado',
            self::NOT_STARTED => 'Cupón aún no válido',
            self::USAGE_LIMIT_EXCEEDED => 'Cupón agotado',
            self::USER_LIMIT_EXCEEDED => 'Has alcanzado el límite de uso de este cupón',
            self::MINIMUM_AMOUNT_NOT_MET => 'No cumples con el monto mínimo requerido',
            self::NOT_FIRST_PURCHASE => 'Este cupón es solo para primera compra',
            self::NO_APPLICABLE_PRODUCTS => 'No hay productos aplicables para este cupón',
            self::ALREADY_APPLIED => 'Ya tienes un cupón aplicado'
        };
    }

    public function isValid(): bool
    {
        return $this === self::VALID;
    }
}