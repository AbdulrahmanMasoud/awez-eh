<?php

declare(strict_types=1);

use App\Support\Casts\PointCast;
use App\Support\Geo\Point;
use App\Support\Geo\PointExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Grammar;

/** What PostgreSQL returns for SRID=4326;POINT(30.5266 30.3811), the centre of Sadat City. */
const SADAT_CITY_EWKB = '0101000020E6100000D712F241CF863E406F8104C58F613E40';

function pointModel(): Model
{
    return new class extends Model {};
}

function readPoint(mixed $stored): ?Point
{
    return new PointCast()->get(pointModel(), 'location', $stored, []);
}

it('reads the EWKB hex PostgreSQL returns, latitude from y and longitude from x', function (): void {
    $point = readPoint(SADAT_CITY_EWKB);

    expect($point?->latitude)->toBe(30.3811)
        ->and($point?->longitude)->toBe(30.5266);
});

it('reads big-endian EWKB and lowercase hex', function (string $stored): void {
    expect(readPoint($stored)?->equals(new Point(30.3811, 30.5266)))->toBeTrue();
})->with([
    'big-endian' => '0020000001000010E6403E86CF41F212D7403E618FC504816F',
    'lowercase' => strtolower(SADAT_CITY_EWKB),
]);

it('reads a missing location as null', function (): void {
    expect(readPoint(null))->toBeNull();
});

it('refuses what is not a geography point in SRID 4326', function (mixed $stored): void {
    expect(fn (): ?Point => readPoint($stored))
        ->toThrow(InvalidArgumentException::class, 'The stored [location] is not a geography point');
})->with([
    'not hexadecimal' => 'POINT(30.5266 30.3811)',
    'an odd number of digits' => substr(SADAT_CITY_EWKB, 0, -1),
    'too short' => substr(SADAT_CITY_EWKB, 0, -2),
    'empty' => '',
    'an unknown byte order' => '02'.substr(SADAT_CITY_EWKB, 2),
    'a line, not a point' => '0102000020E610000001000000D712F241CF863E406F8104C58F613E40',
    'another type of the same length' => '0104000020E6100000D712F241CF863E406F8104C58F613E40',
    'a point with a height' => '01010000A0E6100000D712F241CF863E406F8104C58F613E400000000000000000',
    'Web Mercator, not WGS 84' => '0101000020110F0000D712F241CF863E406F8104C58F613E40',
    'not text' => 42,
]);

it('refuses an empty point, which PostgreSQL writes as NaN coordinates', function (): void {
    expect(fn (): ?Point => readPoint('0101000020E6100000000000000000F87F000000000000F87F'))
        ->toThrow(InvalidArgumentException::class, 'Latitude must be between -90 and 90');
});

it('writes a point as an SQL expression with longitude first', function (): void {
    $written = new PointCast()->set(pointModel(), 'location', new Point(30.3811, 30.5266), []);

    expect($written)->toHaveKey('location')
        ->and($written['location'])->toBeInstanceOf(PointExpression::class)
        ->and($written['location']?->getValue(Mockery::mock(Grammar::class)))
        ->toBe('ST_SetSRID(ST_MakePoint(30.526599999999998, 30.381100000000000), 4326)::geography');
});

it('writes coordinates as plain decimals, never in exponent notation', function (): void {
    $sql = new PointExpression(new Point(-0.00001, 1.0E-7))->getValue(Mockery::mock(Grammar::class));

    expect($sql)->toBe('ST_SetSRID(ST_MakePoint(0.000000100000000, -0.000010000000000), 4326)::geography');
});

it('writes null as null', function (): void {
    expect(new PointCast()->set(pointModel(), 'location', null, []))->toBe(['location' => null]);
});

it('refuses anything but a Point', function (mixed $value): void {
    expect(fn (): array => new PointCast()->set(pointModel(), 'location', $value, []))
        ->toThrow(InvalidArgumentException::class, '[location] takes an instance of '.Point::class);
})->with([
    'well-known text' => 'POINT(30.5266 30.3811)',
    'an array' => [[30.3811, 30.5266]],
    'EWKB' => SADAT_CITY_EWKB,
]);

it('reads back a point written but not saved yet', function (): void {
    $point = new Point(30.3811, 30.5266);

    expect(readPoint(new PointExpression($point)))->toBe($point);
});

it('compares a written point with the stored one by coordinates', function (mixed $first, mixed $second, bool $equal): void {
    expect(new PointCast()->compare(pointModel(), 'location', $first, $second))->toBe($equal);
})->with([
    'stored and written, same place' => [SADAT_CITY_EWKB, new PointExpression(new Point(30.3811, 30.5266)), true],
    'two writes of the same place' => [new PointExpression(new Point(30.3811, 30.5266)), new PointExpression(new Point(30.3811, 30.5266)), true],
    'another place' => [SADAT_CITY_EWKB, new PointExpression(new Point(30.39, 30.5266)), false],
    'latitude and longitude swapped' => [SADAT_CITY_EWKB, new PointExpression(new Point(30.5266, 30.3811)), false],
    'a place and none' => [SADAT_CITY_EWKB, null, false],
    'none and a place' => [null, SADAT_CITY_EWKB, false],
]);
