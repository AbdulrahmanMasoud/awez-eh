<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Blocked = 'blocked';
}
