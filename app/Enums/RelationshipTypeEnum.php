<?php
namespace App\Enums;

enum RelationshipTypeEnum: string
{
    case SIMILAR = 'similar';
    case COMPLEMENTARY = 'complementary';
    case SAME_CATEGORY = 'same_category';
}