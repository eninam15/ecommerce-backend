<?php

namespace App\Enums;

enum CouponType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED_AMOUNT = 'fixed_amount';
    case FREE_SHIPPING = 'free_shipping';
    case CATEGORY_DISCOUNT = 'category_discount';
    case PRODUCT_DISCOUNT = 'product_discount';
    case FIRST_PURCHASE = 'first_purchase';

    public function label(): string
    {
        return match($this) {
            self::PERCENTAGE => 'Descuento por Porcentaje',
            self::FIXED_AMOUNT => 'Descuento Fijo',
            self::FREE_SHIPPING => 'Envío Gratis',
            self::CATEGORY_DISCOUNT => 'Descuento por Categoría',
            self::PRODUCT_DISCOUNT => 'Descuento por Producto',
            self::FIRST_PURCHASE => 'Primera Compra'
        };
    }

    public function description(): string
    {
        return match($this) {
            self::PERCENTAGE => 'Descuento basado en porcentaje del total',
            self::FIXED_AMOUNT => 'Descuento de cantidad fija',
            self::FREE_SHIPPING => 'Elimina costo de envío',
            self::CATEGORY_DISCOUNT => 'Descuento aplicable a categorías específicas',
            self::PRODUCT_DISCOUNT => 'Descuento aplicable a productos específicos',
            self::FIRST_PURCHASE => 'Descuento exclusivo para primera compra'
        };
    }
}