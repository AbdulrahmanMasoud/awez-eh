<?php

declare(strict_types=1);

use App\Domains\Places\Models\OpeningHour;
use App\Domains\Places\Support\OpeningHoursCalculator;
use Carbon\CarbonImmutable;

/**
 * Opening hours as a place's rows hold them: Cairo wall-clock times, 0 for Sunday.
 *
 * @param  list<array{int, string, string}>  $rows
 * @return list<OpeningHour>
 */
function hours(array $rows): array
{
    return array_map(
        fn (array $row): OpeningHour => new OpeningHour(['weekday' => $row[0], 'opens_at' => $row[1], 'closes_at' => $row[2]]),
        $rows,
    );
}

/** A moment given in Cairo time, held in UTC as the app holds every moment. */
function cairo(string $time): CarbonImmutable
{
    return CarbonImmutable::parse($time, 'Africa/Cairo')->utc();
}

function isOpen(array $rows, CarbonImmutable $moment): bool
{
    return new OpeningHoursCalculator('Africa/Cairo')->isOpenAt(hours($rows), $moment);
}

// 2026-11-29 is a Sunday (0), 2026-12-04 a Friday (5) and 2026-12-05 a Saturday (6).

it('is open between opening and closing on the same day', function (string $time, bool $open): void {
    expect(isOpen([[0, '09:00:00', '17:00:00']], cairo($time)))->toBe($open);
})->with([
    'before opening' => ['2026-11-29 08:59:59', false],
    'at opening' => ['2026-11-29 09:00:00', true],
    'in the afternoon' => ['2026-11-29 16:30:00', true],
    'the last second' => ['2026-11-29 16:59:59', true],
    'exactly at closing' => ['2026-11-29 17:00:00', false],
    'the same hours on another day' => ['2026-11-30 12:00:00', false],
]);

it('stays open across midnight into the next day when closing is earlier than opening', function (string $time, bool $open): void {
    // Friday evening until 02:00 on Saturday.
    expect(isOpen([[5, '18:00:00', '02:00:00']], cairo($time)))->toBe($open);
})->with([
    'Friday before opening' => ['2026-12-04 17:59:00', false],
    'Friday night' => ['2026-12-04 23:30:00', true],
    'midnight' => ['2026-12-05 00:00:00', true],
    'Saturday after midnight' => ['2026-12-05 01:59:59', true],
    'exactly at closing on Saturday' => ['2026-12-05 02:00:00', false],
    'Saturday evening, not a Saturday shift' => ['2026-12-05 19:00:00', false],
    'the Friday before, after midnight' => ['2026-12-04 01:00:00', false],
]);

it('wraps from Saturday night into Sunday', function (): void {
    expect(isOpen([[6, '20:00:00', '03:00:00']], cairo('2026-11-29 02:30:00')))->toBeTrue();
});

it('follows split shifts on one day', function (string $time, bool $open): void {
    expect(isOpen([[0, '09:00:00', '13:00:00'], [0, '17:00:00', '23:00:00']], cairo($time)))->toBe($open);
})->with([
    'the morning shift' => ['2026-11-29 10:00:00', true],
    'the afternoon break' => ['2026-11-29 15:00:00', false],
    'the evening shift' => ['2026-11-29 22:59:00', true],
    'after the evening shift' => ['2026-11-29 23:00:00', false],
]);

it('is closed on a day with no hours', function (): void {
    expect(isOpen([[0, '09:00:00', '17:00:00'], [1, '09:00:00', '17:00:00']], cairo('2026-12-05 12:00:00')))->toBeFalse()
        ->and(isOpen([], cairo('2026-11-29 12:00:00')))->toBeFalse();
});

it('reads the same opening and closing time as open for 24 hours', function (string $time, bool $open): void {
    // A pharmacy open around the clock on Sunday.
    expect(isOpen([[0, '00:00:00', '00:00:00']], cairo($time)))->toBe($open);
})->with([
    'Sunday at midnight' => ['2026-11-29 00:00:00', true],
    'Sunday just before midnight' => ['2026-11-29 23:59:59', true],
    'Monday at midnight' => ['2026-11-30 00:00:00', false],
]);

it('reads the moment in Cairo time, summer time included', function (): void {
    // 06:00 UTC is 09:00 in Cairo in July (UTC+3) and 08:00 in January (UTC+2).
    $mornings = [[3, '09:00:00', '17:00:00'], [4, '09:00:00', '17:00:00']];

    expect(isOpen($mornings, CarbonImmutable::parse('2026-07-15 06:00:00', 'UTC')))->toBeTrue()
        ->and(isOpen($mornings, CarbonImmutable::parse('2026-01-15 06:00:00', 'UTC')))->toBeFalse();
});

it('accepts times written without seconds', function (): void {
    expect(isOpen([[0, '09:00', '17:30']], cairo('2026-11-29 17:29:00')))->toBeTrue();
});

it('refuses a time it cannot read', function (): void {
    expect(fn (): bool => isOpen([[0, '9am', '17:00']], cairo('2026-11-29 12:00:00')))
        ->toThrow(InvalidArgumentException::class, 'is not a time of day');
});
