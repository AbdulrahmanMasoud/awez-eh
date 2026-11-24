<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use App\Support\Geo\Point;
use Faker\Generator;

/**
 * Sadat City's centre, and random points spread over the city, for factories and the demo.
 */
final class SadatCity
{
    public const float CENTRE_LATITUDE = 30.3811;

    public const float CENTRE_LONGITUDE = 30.5266;

    /** On the WGS 84 ellipsoid at 30.38° N. */
    private const float METRES_PER_DEGREE_OF_LATITUDE = 110_858.6;

    private const float METRES_PER_DEGREE_OF_LONGITUDE = 96_117.0;

    /** The demo spreads content over this radius, about the size of the city. */
    public const int RADIUS_METRES = 4000;

    public static function centre(): Point
    {
        return new Point(self::CENTRE_LATITUDE, self::CENTRE_LONGITUDE);
    }

    /**
     * A point drawn evenly over the disc around the centre: the square root keeps the outer
     * rings, which are larger, as dense as the middle.
     */
    public static function randomPoint(Generator $faker, int $radiusMetres = self::RADIUS_METRES): Point
    {
        $distance = $radiusMetres * sqrt($faker->randomFloat(6, 0, 1));
        $bearing = $faker->randomFloat(6, 0, 2 * M_PI);

        return new Point(
            self::CENTRE_LATITUDE + $distance * cos($bearing) / self::METRES_PER_DEGREE_OF_LATITUDE,
            self::CENTRE_LONGITUDE + $distance * sin($bearing) / self::METRES_PER_DEGREE_OF_LONGITUDE,
        );
    }

    /**
     * A landline in Menoufia, Sadat City's governorate (048 and seven digits).
     */
    public static function landlineNumber(Generator $faker): string
    {
        return '048'.$faker->numberBetween(2, 9).$faker->numerify('######');
    }

    /**
     * An Egyptian mobile number, random but valid.
     */
    public static function mobileNumber(Generator $faker): string
    {
        return Random::element($faker, ['010', '011', '012', '015']).$faker->numerify('########');
    }
}
