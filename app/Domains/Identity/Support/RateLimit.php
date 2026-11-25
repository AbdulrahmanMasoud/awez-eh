<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use Carbon\CarbonImmutable;

/**
 * At most `max` starts in any `minutes`, over a sliding window. Pure: the caller reads the
 * recent starts, and this says whether one more is allowed and, if not, in how many seconds.
 */
final readonly class RateLimit
{
    public function __construct(
        public int $max,
        public int $minutes,
    ) {}

    public function windowStart(CarbonImmutable $now): CarbonImmutable
    {
        return $now->subMinutes($this->minutes);
    }

    /**
     * Null when one more start is allowed. Otherwise the seconds until the oldest of the last
     * `max` starts leaves the window, at least one.
     *
     * @param  list<CarbonImmutable>  $starts  Any order; those outside the window are ignored.
     */
    public function retryAfterSeconds(array $starts, CarbonImmutable $now): ?int
    {
        $windowStart = $this->windowStart($now);
        $inWindow = array_values(array_filter($starts, fn (CarbonImmutable $start): bool => $start->greaterThan($windowStart)));

        if (count($inWindow) < $this->max) {
            return null;
        }

        usort($inWindow, fn (CarbonImmutable $a, CarbonImmutable $b): int => $b <=> $a);
        $leavesAt = $inWindow[$this->max - 1]->addMinutes($this->minutes);

        return max(1, (int) ceil($now->diffInSeconds($leavesAt)));
    }

    /**
     * @param  array{max: int, minutes: int}  $limit
     */
    public static function fromConfig(array $limit): self
    {
        return new self($limit['max'], $limit['minutes']);
    }
}
