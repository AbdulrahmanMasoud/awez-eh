<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Enums;

/**
 * How content can end in a deal: a sale ends as sold, a rent as rented. Content that is neither
 * (a craftsman's profile, a place) has no deal and is only ever archived.
 */
enum ContentDeal: string
{
    case Sale = 'sale';
    case Rent = 'rent';
}
