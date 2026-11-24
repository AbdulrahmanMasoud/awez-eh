<?php

declare(strict_types=1);

use App\Domains\Promotions\Models\FeaturedPlacement;
use Carbon\CarbonImmutable;

it('is active from its start up to, not including, its end, unless cancelled', function (string $now, bool $active): void {
    FeaturedPlacement::factory()->create(['starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-08 10:00:00']);
    $this->travelTo(CarbonImmutable::parse($now, 'UTC'));

    expect(FeaturedPlacement::query()->active()->exists())->toBe($active);
})->with([
    'a second before it starts' => ['2026-12-01 09:59:59', false],
    'when it starts' => ['2026-12-01 10:00:00', true],
    'in the middle' => ['2026-12-04 18:00:00', true],
    'a second before it ends' => ['2026-12-08 09:59:59', true],
    'when it ends' => ['2026-12-08 10:00:00', false],
]);

it('is not active once cancelled', function (): void {
    FeaturedPlacement::factory()->create([
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(6),
        'cancelled_at' => now()->subHour(),
    ]);

    expect(FeaturedPlacement::query()->active()->exists())->toBeFalse();
});
