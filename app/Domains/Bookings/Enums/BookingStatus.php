<?php

declare(strict_types=1);

namespace App\Domains\Bookings\Enums;

/**
 * Where a booking is. `on_the_way` and `in_progress` are for service visits only; `no_show` means the
 * resident did not show up. Transitions and who may make them: BookingStateMachine.
 */
enum BookingStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case OnTheWay = 'on_the_way';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CancelledByResident = 'cancelled_by_resident';
    case CancelledByCounterpart = 'cancelled_by_counterpart';
    case NoShow = 'no_show';
}
