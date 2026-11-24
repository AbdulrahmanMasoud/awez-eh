<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Enums;

/**
 * How far a property is finished inside, as Egyptian listings say it.
 */
enum Finishing: string
{
    case Unfinished = 'unfinished';
    case SemiFinished = 'semi_finished';
    case Finished = 'finished';
    case Luxury = 'luxury';
}
