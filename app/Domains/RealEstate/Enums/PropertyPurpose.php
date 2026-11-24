<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Enums;

enum PropertyPurpose: string
{
    case Sale = 'sale';
    case Rent = 'rent';
}
