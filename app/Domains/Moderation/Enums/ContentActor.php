<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Enums;

/**
 * Who moves moderated content: its owner (through the API), or a moderator (in the panel).
 */
enum ContentActor: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
}
