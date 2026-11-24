<?php

declare(strict_types=1);

namespace App\Domains\Promotions\Enums;

/**
 * The kinds of content a featured package applies to, named as in the morph map.
 */
enum FeaturableType: string
{
    case Property = 'property';
    case Provider = 'provider';
    case Place = 'place';
    case UsedItem = 'used_item';
}
