<?php

declare(strict_types=1);

use App\Support\Casts\PointCast;
use App\Support\Geo\HasLocation;
use App\Support\Geo\Point;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Unguarded]
#[Table(name: 'location_probes')]
#[WithoutTimestamps]
final class LocationProbe extends Model
{
    use HasLocation;

    protected function casts(): array
    {
        return ['location' => PointCast::class];
    }
}

/** Metres in one degree of latitude at Sadat City (30.38° N) on the WGS 84 ellipsoid. */
const METRES_PER_DEGREE_OF_LATITUDE = 110_858.6;

function sadatCity(): Point
{
    return new Point(30.3811, 30.5266);
}

function northOfSadatCity(int $metres): Point
{
    return new Point(30.3811 + $metres / METRES_PER_DEGREE_OF_LATITUDE, 30.5266);
}

beforeEach(function (): void {
    // Created inside the test's transaction, so it disappears with it: no migration needed.
    Schema::create('location_probes', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->geography('location', subtype: 'point', srid: 4326)->nullable();
    });
});

it('stores a point with longitude as x and latitude as y', function (): void {
    $probe = LocationProbe::query()->create(['location' => sadatCity()]);

    $stored = DB::selectOne(
        'select ST_X(location::geometry) as x, ST_Y(location::geometry) as y, ST_AsText(location) as text from location_probes where id = ?',
        [$probe->id],
    );

    expect($stored->x)->toBe(30.5266)
        ->and($stored->y)->toBe(30.3811)
        ->and($stored->text)->toBe('POINT(30.5266 30.3811)');
});

it('reads back exactly the point it wrote', function (): void {
    $point = new Point(30.381112345678901, 30.526698765432109);

    $probe = LocationProbe::query()->create(['location' => $point]);

    $stored = LocationProbe::query()->findOrFail($probe->id)->location;

    expect($stored)->toBeInstanceOf(Point::class)
        ->and($stored->latitude)->toBe($point->latitude)
        ->and($stored->longitude)->toBe($point->longitude);
});

it('stores a missing location as null', function (): void {
    $probe = LocationProbe::query()->create(['location' => null]);

    expect(LocationProbe::query()->findOrFail($probe->id)->location)->toBeNull();
});

it('does not see a change when the location is read or set to the same place', function (): void {
    $probe = LocationProbe::query()->create(['location' => sadatCity()]);
    $stored = LocationProbe::query()->findOrFail($probe->id);

    $stored->location;
    expect($stored->isDirty())->toBeFalse();

    $stored->location = sadatCity();
    expect($stored->isDirty())->toBeFalse();

    $stored->location = northOfSadatCity(10);
    expect($stored->isDirty('location'))->toBeTrue();
});

it('finds the rows within a distance and orders them nearest first, in SQL', function (): void {
    // Inserted far to near, so the order comes from the query and not from insertion.
    foreach ([3000 => 'far', 1500 => 'middle', 500 => 'near'] as $metres => $name) {
        LocationProbe::query()->create(['name' => $name, 'location' => northOfSadatCity($metres)]);
    }
    LocationProbe::query()->create(['name' => 'nowhere', 'location' => null]);

    $names = fn (int $metres): array => LocationProbe::query()
        ->withinMeters(sadatCity(), $metres)
        ->orderByDistance(sadatCity())
        ->pluck('name')
        ->all();

    expect($names(400))->toBe([])
        ->and($names(1000))->toBe(['near'])
        ->and($names(2000))->toBe(['near', 'middle'])
        ->and($names(5000))->toBe(['near', 'middle', 'far']);
});

it('measures the distances on the ellipsoid, as the scopes compare them', function (): void {
    $probe = LocationProbe::query()->create(['location' => northOfSadatCity(1500)]);

    $metres = (float) DB::scalar(
        'select ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) from location_probes where id = ?',
        [30.5266, 30.3811, $probe->id],
    );

    expect($metres)->toEqualWithDelta(1500.0, 1.0);
});

it('runs both scopes in the query, never in PHP', function (): void {
    $sql = LocationProbe::query()->withinMeters(sadatCity(), 2000)->orderByDistance(sadatCity())->toRawSql();

    expect($sql)->toContain('ST_DWithin(location, ST_SetSRID(ST_MakePoint(30.5266, 30.3811), 4326)::geography, 2000)')
        ->and($sql)->toContain('order by ST_Distance(location, ST_SetSRID(ST_MakePoint(30.5266, 30.3811), 4326)::geography)');
});
