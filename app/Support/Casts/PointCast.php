<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\Geo\Point;
use App\Support\Geo\PointExpression;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A Point in a geography(Point, 4326) column (CLAUDE.md invariant 5).
 *
 * Written as an SQL expression (PointExpression), read from the EWKB hex PostgreSQL returns.
 * compare() looks at coordinates, so a written point equal to the stored one is not a change.
 *
 * @implements CastsAttributes<Point, mixed>
 */
final readonly class PointCast implements CastsAttributes, ComparesCastableAttributes
{
    /** Byte order, type, SRID and two doubles. */
    private const int EWKB_POINT_BYTES = 25;

    /** A 2D point (type 1) with the flag that says an SRID follows. */
    private const int POINT_WITH_SRID = 0x20000001;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Point
    {
        return $this->toPoint($key, $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, PointExpression|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (! $value instanceof Point) {
            throw new InvalidArgumentException("[{$key}] takes an instance of ".Point::class.'.');
        }

        return [$key => new PointExpression($value)];
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        $first = $this->toPoint($key, $firstValue);
        $second = $this->toPoint($key, $secondValue);

        if (! $first instanceof Point || ! $second instanceof Point) {
            return $first === $second;
        }

        return $first->equals($second);
    }

    private function toPoint(string $key, mixed $value): ?Point
    {
        return match (true) {
            $value === null => null,
            $value instanceof PointExpression => $value->point,
            is_string($value) => $this->parse($key, $value),
            default => throw $this->notAPoint($key),
        };
    }

    private function parse(string $key, string $hex): Point
    {
        $bytes = ctype_xdigit($hex) && strlen($hex) === 2 * self::EWKB_POINT_BYTES ? hex2bin($hex) : false;

        if ($bytes === false) {
            throw $this->notAPoint($key);
        }

        // The first byte says how the rest is ordered: 1 little-endian, 0 big-endian.
        $littleEndian = match ($bytes[0]) {
            "\x01" => true,
            "\x00" => false,
            default => throw $this->notAPoint($key),
        };

        $header = unpack($littleEndian ? 'Vtype/Vsrid' : 'Ntype/Nsrid', $bytes, 1);

        if ($header === false || $header['type'] !== self::POINT_WITH_SRID || $header['srid'] !== Point::SRID) {
            throw $this->notAPoint($key);
        }

        // x is the longitude and y the latitude.
        $coordinates = unpack($littleEndian ? 'ex/ey' : 'Ex/Ey', $bytes, 9);

        if ($coordinates === false || ! is_float($coordinates['x']) || ! is_float($coordinates['y'])) {
            throw $this->notAPoint($key);
        }

        return new Point(latitude: $coordinates['y'], longitude: $coordinates['x']);
    }

    private function notAPoint(string $key): InvalidArgumentException
    {
        return new InvalidArgumentException("The stored [{$key}] is not a geography point in SRID ".Point::SRID.' (EWKB hex).');
    }
}
