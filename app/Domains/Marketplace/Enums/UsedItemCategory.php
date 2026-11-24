<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Enums;

enum UsedItemCategory: string
{
    case Furniture = 'furniture';
    case Appliances = 'appliances';
    case Electronics = 'electronics';
    case Mobiles = 'mobiles';
    case Vehicles = 'vehicles';
    case Clothes = 'clothes';
    case Kids = 'kids';
    case Books = 'books';
    case Other = 'other';
}
