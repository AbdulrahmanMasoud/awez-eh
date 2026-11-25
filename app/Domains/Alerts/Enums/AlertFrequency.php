<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

/**
 * How often a search alert notifies: at once, or once a day.
 */
enum AlertFrequency: string
{
    case Instant = 'instant';
    case Daily = 'daily';
}
