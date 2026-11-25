<?php

declare(strict_types=1);

use App\Domains\Identity\Support\RateLimit;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->now = CarbonImmutable::parse('2026-11-25 18:00:00');
    $this->limit = new RateLimit(max: 3, minutes: 10);
});

it('allows starts below the limit', function (): void {
    expect($this->limit->retryAfterSeconds([], $this->now))->toBeNull()
        ->and($this->limit->retryAfterSeconds([$this->now->subMinute(), $this->now->subMinutes(2)], $this->now))->toBeNull();
});

it('refuses the start over the limit until the oldest counted one leaves the window', function (): void {
    $starts = [$this->now->subMinutes(9), $this->now->subMinutes(5), $this->now->subMinute()];

    expect($this->limit->retryAfterSeconds($starts, $this->now))->toBe(60);
});

it('reads the starts in any order', function (): void {
    $starts = [$this->now->subMinute(), $this->now->subMinutes(9), $this->now->subMinutes(5)];

    expect($this->limit->retryAfterSeconds($starts, $this->now))->toBe(60);
});

it('waits for the newest starts only when more than the limit are in the window', function (): void {
    $starts = [$this->now->subMinutes(9), $this->now->subMinutes(8), $this->now->subMinutes(4), $this->now->subMinutes(2)];

    // The third newest, at 17:52, leaves the window at 18:02.
    expect($this->limit->retryAfterSeconds($starts, $this->now))->toBe(120);
});

it('ignores starts outside the window, the edge included', function (): void {
    $starts = [$this->now->subMinutes(10), $this->now->subMinutes(30), $this->now->subMinute(), $this->now->subMinutes(2)];

    expect($this->limit->retryAfterSeconds($starts, $this->now))->toBeNull();
});

it('asks to wait at least a second', function (): void {
    $starts = [$this->now->subMinutes(10)->addMilliseconds(200), $this->now->subMinutes(5), $this->now->subMinute()];

    expect($this->limit->retryAfterSeconds($starts, $this->now))->toBe(1);
});

it('reads a limit from config', function (): void {
    $limit = RateLimit::fromConfig(['max' => 10, 'minutes' => 1440]);

    expect($limit->max)->toBe(10)
        ->and($limit->minutes)->toBe(1440)
        ->and($limit->windowStart($this->now)->toDateTimeString())->toBe('2026-11-24 18:00:00');
});
