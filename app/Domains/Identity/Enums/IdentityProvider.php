<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

enum IdentityProvider: string
{
    case Google = 'google';
    case Apple = 'apple';
}
