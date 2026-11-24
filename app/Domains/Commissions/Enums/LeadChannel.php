<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Enums;

/**
 * How a resident reached a brokered listing.
 */
enum LeadChannel: string
{
    case Chat = 'chat';
    case Viewing = 'viewing';
}
