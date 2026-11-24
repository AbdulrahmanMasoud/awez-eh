<?php

declare(strict_types=1);

namespace App\Domains\Requests\Enums;

/**
 * A request is public while open. Its owner closes it, or it expires at `expires_at`.
 */
enum RequestStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Expired = 'expired';
}
