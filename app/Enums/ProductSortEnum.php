<?php
namespace App\Enums;

enum ProductSortEnum: string {
    case MOST_SOLD = 'most_sold';
    case LATEST = 'latest';
    case PRICE_ASC = 'price_asc';
    case PRICE_DESC = 'price_desc';
    case MOST_RATED = 'most_rated';
    case PROMOTION = 'promotion';
}
