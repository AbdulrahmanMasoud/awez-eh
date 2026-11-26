<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Enums;

/**
 * How the public list is ordered. Each one ends on the id, so cursor pagination is stable.
 */
enum PropertySort: string
{
    case Newest = 'newest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    /** Needs a location to measure from. */
    case Nearest = 'nearest';
}
