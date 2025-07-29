<?php

namespace App\Enums;

enum StockMovementType: string
{
    case RESERVE = 'reserve';
    case RELEASE = 'release';
    case REDUCE = 'reduce';
    case RESTOCK = 'restock';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match($this) {
            self::RESERVE => 'Reservado',
            self::RELEASE => 'Liberado', 
            self::REDUCE => 'Reducido',
            self::RESTOCK => 'Reposición',
            self::ADJUSTMENT => 'Ajuste Manual'
        };
    }
}

enum StockMovementReason: string
{
    case CART_ADD = 'cart_add';
    case CART_REMOVE = 'cart_remove';
    case ORDER_CREATE = 'order_create';
    case PAYMENT_CONFIRM = 'payment_confirm';
    case ORDER_CANCEL = 'order_cancel';
    case ORDER_RETURN = 'order_return';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';
    case EXPIRED_RESERVATION = 'expired_reservation';
    case RESTOCK = 'restock';

    public function label(): string
    {
        return match($this) {
            self::CART_ADD => 'Agregado al carrito',
            self::CART_REMOVE => 'Removido del carrito',
            self::ORDER_CREATE => 'Orden creada',
            self::PAYMENT_CONFIRM => 'Pago confirmado',
            self::ORDER_CANCEL => 'Orden cancelada',
            self::ORDER_RETURN => 'Devolución',
            self::MANUAL_ADJUSTMENT => 'Ajuste manual',
            self::EXPIRED_RESERVATION => 'Reserva expirada',
            self::RESTOCK => 'Reposición de stock'
        };
    }
}

enum StockReservationStatus: string
{
    case ACTIVE = 'active';
    case CONFIRMED = 'confirmed';
    case EXPIRED = 'expired';
    case RELEASED = 'released';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Activa',
            self::CONFIRMED => 'Confirmada',
            self::EXPIRED => 'Expirada',
            self::RELEASED => 'Liberada'
        };
    }
}