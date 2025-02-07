<?php
namespace App\Enums;

enum PromotionTypeEnum: string
{
    case DISCOUNT = 'discount';
    case COMBO = 'combo';
    case SEASONAL = 'seasonal';
}