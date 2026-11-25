<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * Deleted accounts keep their row, with the personal data blanked (DECISIONS 56).
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';
}
