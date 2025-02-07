<?php
namespace App\Enums;

enum DiscountTypeEnum: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
}