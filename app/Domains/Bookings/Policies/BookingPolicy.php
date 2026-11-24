<?php

declare(strict_types=1);

namespace App\Domains\Bookings\Policies;

use App\Domains\Bookings\Models\Booking;
use App\Domains\Identity\Models\User;

/**
 * What a resident may do with a booking through the API: its resident and its counterpart see it
 * and move it (through BookingStateMachine, in step 3.1). A brokered viewing has no counterpart:
 * the platform runs it in Filament.
 */
final class BookingPolicy
{
    public function view(?User $user, Booking $booking): bool
    {
        return $booking->resident()->is($user) || $booking->counterpart()->is($user);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $booking->resident()->is($user) || $booking->counterpart()->is($user);
    }

    /**
     * Nobody deletes a booking: its events are history.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return false;
    }
}
