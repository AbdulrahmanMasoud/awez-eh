<?php

declare(strict_types=1);

namespace App\Domains\Places\Support;

use App\Domains\Places\Models\OpeningHour;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Whether a place is open at a moment. Pure: it reads opening hours already loaded and does no I/O.
 *
 * Hours are wall-clock times in the city's zone, so the moment is read in that zone first, with
 * its summer time. An interval is open from its opening time up to, not including, its closing
 * time; one that closes earlier than it opens ends on the next day, and one that closes when it
 * opens lasts 24 hours.
 */
final readonly class OpeningHoursCalculator
{
    private const int SECONDS_PER_DAY = 86_400;

    private const int DAYS_PER_WEEK = 7;

    public function __construct(private string $timezone) {}

    /**
     * @param  iterable<OpeningHour>  $hours
     */
    public function isOpenAt(iterable $hours, CarbonImmutable $moment): bool
    {
        $local = $moment->setTimezone($this->timezone);
        $weekday = $local->dayOfWeek;
        // Wall-clock seconds, not seconds elapsed since midnight: they differ on the two days a
        // year the clocks change, and Egypt changes them at midnight.
        $second = $local->hour * 3600 + $local->minute * 60 + $local->second;

        foreach ($hours as $hour) {
            if ($this->covers($hour, $weekday, $second)) {
                return true;
            }
        }

        return false;
    }

    private function covers(OpeningHour $hour, int $weekday, int $second): bool
    {
        $opens = $this->secondOfDay($hour->opens_at);
        $closes = $this->secondOfDay($hour->closes_at);

        if ($opens < $closes) {
            return $hour->weekday === $weekday && $second >= $opens && $second < $closes;
        }

        // From the opening time to the end of the day, then from midnight to the closing time.
        $nextDay = ($hour->weekday + 1) % self::DAYS_PER_WEEK;

        return ($hour->weekday === $weekday && $second >= $opens)
            || ($nextDay === $weekday && $second < $closes);
    }

    private function secondOfDay(string $time): int
    {
        if (preg_match('/\A([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?\z/', $time, $parts) !== 1) {
            throw new InvalidArgumentException("[{$time}] is not a time of day (HH:MM or HH:MM:SS).");
        }

        $second = (int) $parts[1] * 3600 + (int) $parts[2] * 60 + (int) ($parts[3] ?? 0);

        return $second % self::SECONDS_PER_DAY;
    }
}
