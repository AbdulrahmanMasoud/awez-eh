<?php

declare(strict_types=1);

namespace App\Support\Geo;

use InvalidArgumentException;

/**
 * A place on the globe in WGS 84 (SRID 4326), latitude first as people say it.
 *
 * Coordinates are floats: unlike money, nothing adds them up, and PostGIS stores them as doubles.
 */
final readonly class Point
{
    public const int SRID = 4326;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        // Every comparison with NaN is false, so NaN would slip past the range check alone. The
        // messages leave the value out: a resident's pin can be their home, and exceptions reach
        // logs and the error tracker.
        if (is_nan($latitude) || $latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90.');
        }

        if (is_nan($longitude) || $longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }
}
