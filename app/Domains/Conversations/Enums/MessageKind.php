<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Enums;

/**
 * A message is text, a shared location, or a system notice (a booking confirmed, ...).
 */
enum MessageKind: string
{
    case Text = 'text';
    case Location = 'location';
    case System = 'system';
}
