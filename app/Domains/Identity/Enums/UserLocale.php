<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * The language a resident reads notifications in; the API itself follows Accept-Language.
 */
enum UserLocale: string
{
    case Arabic = 'ar';
    case English = 'en';
}
