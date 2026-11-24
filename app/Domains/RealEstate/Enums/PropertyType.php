<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Enums;

enum PropertyType: string
{
    case Apartment = 'apartment';
    case Villa = 'villa';
    case Duplex = 'duplex';
    case House = 'house';
    case Land = 'land';
    case Shop = 'shop';
    case Office = 'office';
    case Warehouse = 'warehouse';
    case Factory = 'factory';
}
