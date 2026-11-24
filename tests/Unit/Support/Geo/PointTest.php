<?php

declare(strict_types=1);

use App\Support\Geo\Point;

it('keeps latitude and longitude as given', function (): void {
    $point = new Point(30.3811, 30.5266);

    expect($point->latitude)->toBe(30.3811)
        ->and($point->longitude)->toBe(30.5266);
});

it('accepts the edges of the globe', function (float $latitude, float $longitude): void {
    $point = new Point($latitude, $longitude);

    expect([$point->latitude, $point->longitude])->toBe([$latitude, $longitude]);
})->with([
    'south-west' => [-90.0, -180.0],
    'north-east' => [90.0, 180.0],
    'the origin' => [0.0, 0.0],
]);

it('refuses coordinates outside the globe', function (float $latitude, float $longitude, string $message): void {
    expect(fn (): Point => new Point($latitude, $longitude))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'latitude above 90' => [90.000001, 30.0, 'Latitude must be between -90 and 90'],
    'latitude below -90' => [-90.5, 30.0, 'Latitude must be between -90 and 90'],
    'longitude above 180' => [30.0, 180.1, 'Longitude must be between -180 and 180'],
    'longitude below -180' => [30.0, -181.0, 'Longitude must be between -180 and 180'],
    'latitude not a number' => [NAN, 30.0, 'Latitude must be between -90 and 90'],
    'longitude infinite' => [30.0, INF, 'Longitude must be between -180 and 180'],
]);

it('compares by coordinates', function (): void {
    $point = new Point(30.3811, 30.5266);

    expect($point->equals(new Point(30.3811, 30.5266)))->toBeTrue()
        ->and($point->equals(new Point(30.5266, 30.3811)))->toBeFalse();
});
