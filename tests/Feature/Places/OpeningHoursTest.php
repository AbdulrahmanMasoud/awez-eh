<?php

declare(strict_types=1);

use App\Domains\Places\Models\OpeningHour;
use App\Domains\Places\Models\Place;
use Carbon\CarbonImmutable;

it('tells whether a place is open from the hours stored for it', function (): void {
    $place = Place::factory()->create();
    OpeningHour::factory()->for($place)->create(['weekday' => 4, 'opens_at' => '10:00', 'closes_at' => '01:00']);

    // Thursday 2026-12-03 23:00 in Cairo is 21:00 UTC; Friday 01:00 in Cairo is Thursday 23:00 UTC.
    expect($place->isOpenAt(CarbonImmutable::parse('2026-12-03 21:00:00', 'UTC')))->toBeTrue()
        ->and($place->isOpenAt(CarbonImmutable::parse('2026-12-03 23:00:00', 'UTC')))->toBeFalse()
        ->and($place->isOpenAt(CarbonImmutable::parse('2026-12-03 07:59:00', 'UTC')))->toBeFalse();
});

it('stores opening hours as times of day', function (): void {
    $hour = OpeningHour::factory()->create(['opens_at' => '09:30', 'closes_at' => '23:00']);

    expect(OpeningHour::query()->findOrFail($hour->id)->only(['opens_at', 'closes_at']))
        ->toBe(['opens_at' => '09:30:00', 'closes_at' => '23:00:00']);
});
