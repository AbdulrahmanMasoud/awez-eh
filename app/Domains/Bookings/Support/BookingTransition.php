<?php

declare(strict_types=1);

namespace App\Domains\Bookings\Support;

use App\Domains\Bookings\Enums\BookingActor;
use App\Domains\Bookings\Enums\BookingKind;
use App\Domains\Bookings\Enums\BookingStatus;

/**
 * One allowed move of BookingStateMachine: from a status to another, for some kinds of booking, by
 * some actors.
 */
final readonly class BookingTransition
{
    /**
     * @param  list<BookingKind>  $kinds
     * @param  list<BookingActor>  $actors
     */
    public function __construct(
        public BookingStatus $from,
        public BookingStatus $to,
        public array $kinds,
        public array $actors,
    ) {}

    public function allows(BookingKind $kind, BookingStatus $from, BookingStatus $to, BookingActor $actor): bool
    {
        return $this->from === $from
            && $this->to === $to
            && in_array($kind, $this->kinds, true)
            && in_array($actor, $this->actors, true);
    }
}
