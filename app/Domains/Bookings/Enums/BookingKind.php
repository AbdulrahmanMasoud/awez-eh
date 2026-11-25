<?php

declare(strict_types=1);

namespace App\Domains\Bookings\Enums;

/**
 * A viewing of a property, or a craftsman's visit.
 */
enum BookingKind: string
{
    case PropertyViewing = 'property_viewing';
    case ServiceVisit = 'service_visit';
}
