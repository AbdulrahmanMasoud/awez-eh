<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
}
