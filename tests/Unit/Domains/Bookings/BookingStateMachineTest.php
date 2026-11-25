<?php

declare(strict_types=1);

use App\Domains\Bookings\Enums\BookingActor;
use App\Domains\Bookings\Enums\BookingKind;
use App\Domains\Bookings\Enums\BookingStatus;
use App\Domains\Bookings\Support\BookingStateMachine;
use App\Domains\Bookings\Support\BookingTransition;

mutates(BookingStateMachine::class, BookingTransition::class);

/**
 * Who may move a booking from one status to another, by kind: the specification, written here
 * apart from the machine. Every pair not listed is refused to everyone.
 *
 * @return array<string, list<BookingActor>>
 */
function allowedBookingTransitions(): array
{
    $counterpartOrPlatform = [BookingActor::Counterpart, BookingActor::Platform];
    $both = ['property_viewing', 'service_visit'];
    $rules = [
        ['requested', 'confirmed', $both, $counterpartOrPlatform],
        ['requested', 'declined', $both, [BookingActor::Counterpart, BookingActor::Platform, BookingActor::System]],
        ['requested', 'cancelled_by_resident', $both, [BookingActor::Resident]],
        ['confirmed', 'completed', ['property_viewing'], $counterpartOrPlatform],
        ['confirmed', 'on_the_way', ['service_visit'], [BookingActor::Counterpart]],
        ['confirmed', 'in_progress', ['service_visit'], [BookingActor::Counterpart]],
        ['confirmed', 'cancelled_by_resident', $both, [BookingActor::Resident]],
        ['confirmed', 'cancelled_by_counterpart', $both, $counterpartOrPlatform],
        ['confirmed', 'no_show', $both, $counterpartOrPlatform],
        ['on_the_way', 'in_progress', ['service_visit'], [BookingActor::Counterpart]],
        ['on_the_way', 'cancelled_by_resident', ['service_visit'], [BookingActor::Resident]],
        ['on_the_way', 'cancelled_by_counterpart', ['service_visit'], $counterpartOrPlatform],
        ['on_the_way', 'no_show', ['service_visit'], [BookingActor::Counterpart]],
        ['in_progress', 'completed', ['service_visit'], [BookingActor::Counterpart]],
    ];
    $allowed = [];

    foreach ($rules as [$from, $to, $kinds, $actors]) {
        foreach ($kinds as $kind) {
            $allowed["{$kind} {$from} → {$to}"] = $actors;
        }
    }

    return $allowed;
}

/**
 * Every kind and pair of statuses, allowed or not: 2 × 9 × 9.
 *
 * @return array<string, array{BookingKind, BookingStatus, BookingStatus}>
 */
function everyBookingTransition(): array
{
    $pairs = [];

    foreach (BookingKind::cases() as $kind) {
        foreach (BookingStatus::cases() as $from) {
            foreach (BookingStatus::cases() as $to) {
                $pairs["{$kind->value} {$from->value} → {$to->value}"] = [$kind, $from, $to];
            }
        }
    }

    return $pairs;
}

it('lets exactly the right actors make each transition, and nobody else', function (BookingKind $kind, BookingStatus $from, BookingStatus $to): void {
    $machine = new BookingStateMachine;
    $expected = allowedBookingTransitions()["{$kind->value} {$from->value} → {$to->value}"] ?? [];
    $allowed = array_values(array_filter(
        BookingActor::cases(),
        fn (BookingActor $actor): bool => $machine->canTransition($kind, $from, $to, $actor),
    ));

    expect($allowed)->toBe($expected);
})->with(everyBookingTransition());

it('ends a booking for good in its terminal statuses', function (BookingStatus $status, bool $terminal): void {
    expect(new BookingStateMachine()->isTerminal($status))->toBe($terminal);
})->with([
    'requested' => [BookingStatus::Requested, false],
    'confirmed' => [BookingStatus::Confirmed, false],
    'on the way' => [BookingStatus::OnTheWay, false],
    'in progress' => [BookingStatus::InProgress, false],
    'declined' => [BookingStatus::Declined, true],
    'completed' => [BookingStatus::Completed, true],
    'cancelled by the resident' => [BookingStatus::CancelledByResident, true],
    'cancelled by the counterpart' => [BookingStatus::CancelledByCounterpart, true],
    'no show' => [BookingStatus::NoShow, true],
]);

it('keeps the statuses the brief names, in snake case', function (): void {
    expect(array_column(BookingStatus::cases(), 'value'))->toBe([
        'requested', 'confirmed', 'declined', 'on_the_way', 'in_progress',
        'completed', 'cancelled_by_resident', 'cancelled_by_counterpart', 'no_show',
    ]);
});
