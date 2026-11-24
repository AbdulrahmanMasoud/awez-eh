<?php

declare(strict_types=1);

namespace App\Domains\Requests\Enums;

/**
 * The part of the app a resident's request is about, which decides who sees it.
 */
enum RequestArea: string
{
    case RealEstate = 'real_estate';
    case Services = 'services';
    case Places = 'places';
    case Marketplace = 'marketplace';
    case Other = 'other';
}
