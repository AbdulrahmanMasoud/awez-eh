<?php

declare(strict_types=1);

namespace App\Domains\Services\Enums;

/**
 * What a craftsman's price is for.
 */
enum PriceUnit: string
{
    case Visit = 'visit';
    case Hour = 'hour';
    case Job = 'job';
    case SquareMeter = 'square_meter';
}
