<?php

declare(strict_types=1);

namespace App\Domains\Bookings\Enums;

/**
 * Who moves a booking: the resident who booked, the counterpart (the owner or the craftsman), the
 * platform's staff (who run brokered viewings), or the system (scheduled jobs).
 */
enum BookingActor: string
{
    case Resident = 'resident';
    case Counterpart = 'counterpart';
    case Platform = 'platform';
    case System = 'system';
}
