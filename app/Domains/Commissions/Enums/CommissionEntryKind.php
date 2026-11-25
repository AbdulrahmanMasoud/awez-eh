<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Enums;

/**
 * One line of a property's commission history. `cancelled` takes an amount off what was expected (a deal
 * fell through). CommissionBalance adds them up.
 */
enum CommissionEntryKind: string
{
    case Expected = 'expected';
    case Invoiced = 'invoiced';
    case Collected = 'collected';
    case Cancelled = 'cancelled';
}
