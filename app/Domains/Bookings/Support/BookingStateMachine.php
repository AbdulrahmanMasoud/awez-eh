<?php

declare(strict_types=1);

namespace App\Domains\Bookings\Support;

use App\Domains\Bookings\Enums\BookingActor;
use App\Domains\Bookings\Enums\BookingKind;
use App\Domains\Bookings\Enums\BookingStatus;

/**
 * The statuses a booking moves through, and who may move it. Pure: the Actions of step 3.1 ask it
 * before they change a booking and record the event.
 *
 * Every transition not listed is refused. A terminal status allows none.
 */
final class BookingStateMachine
{
    public function canTransition(BookingKind $kind, BookingStatus $from, BookingStatus $to, BookingActor $actor): bool
    {
        return array_any($this->transitions(), fn (BookingTransition $transition): bool => $transition->allows($kind, $from, $to, $actor));
    }

    public function isTerminal(BookingStatus $status): bool
    {
        return match ($status) {
            BookingStatus::Requested, BookingStatus::Confirmed, BookingStatus::OnTheWay, BookingStatus::InProgress => false,
            BookingStatus::Declined, BookingStatus::Completed, BookingStatus::CancelledByResident, BookingStatus::CancelledByCounterpart, BookingStatus::NoShow => true,
        };
    }

    /**
     * Built in a method, not a constant: mutation testing runs only the tests that cover a line,
     * and line coverage never reaches the inside of a constant.
     *
     * @return list<BookingTransition>
     */
    private function transitions(): array
    {
        $both = [BookingKind::PropertyViewing, BookingKind::ServiceVisit];
        $visit = [BookingKind::ServiceVisit];
        $viewing = [BookingKind::PropertyViewing];
        $resident = [BookingActor::Resident];
        $counterpart = [BookingActor::Counterpart];
        $counterpartOrPlatform = [BookingActor::Counterpart, BookingActor::Platform];

        return [
            new BookingTransition(BookingStatus::Requested, BookingStatus::Confirmed, $both, $counterpartOrPlatform),
            // The system declines a request nobody answered before its time.
            new BookingTransition(BookingStatus::Requested, BookingStatus::Declined, $both, [BookingActor::Counterpart, BookingActor::Platform, BookingActor::System]),
            new BookingTransition(BookingStatus::Requested, BookingStatus::CancelledByResident, $both, $resident),
            new BookingTransition(BookingStatus::Confirmed, BookingStatus::Completed, $viewing, $counterpartOrPlatform),
            new BookingTransition(BookingStatus::Confirmed, BookingStatus::OnTheWay, $visit, $counterpart),
            // A craftsman who lives next door starts without being on the way.
            new BookingTransition(BookingStatus::Confirmed, BookingStatus::InProgress, $visit, $counterpart),
            new BookingTransition(BookingStatus::Confirmed, BookingStatus::CancelledByResident, $both, $resident),
            new BookingTransition(BookingStatus::Confirmed, BookingStatus::CancelledByCounterpart, $both, $counterpartOrPlatform),
            new BookingTransition(BookingStatus::Confirmed, BookingStatus::NoShow, $both, $counterpartOrPlatform),
            new BookingTransition(BookingStatus::OnTheWay, BookingStatus::InProgress, $visit, $counterpart),
            new BookingTransition(BookingStatus::OnTheWay, BookingStatus::CancelledByResident, $visit, $resident),
            new BookingTransition(BookingStatus::OnTheWay, BookingStatus::CancelledByCounterpart, $visit, $counterpartOrPlatform),
            new BookingTransition(BookingStatus::OnTheWay, BookingStatus::NoShow, $visit, $counterpart),
            new BookingTransition(BookingStatus::InProgress, BookingStatus::Completed, $visit, $counterpart),
        ];
    }
}
