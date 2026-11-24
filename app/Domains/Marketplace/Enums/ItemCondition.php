<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Enums;

enum ItemCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Good = 'good';
    case Fair = 'fair';
}
