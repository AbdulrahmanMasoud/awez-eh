<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Enums;

/**
 * How often the rent of a property for rent is paid.
 */
enum RentPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
