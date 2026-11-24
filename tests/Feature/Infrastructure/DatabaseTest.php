<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('runs on PostgreSQL 18', function (): void {
    $versionNumber = (int) DB::scalar("select current_setting('server_version_num')");

    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(intdiv($versionNumber, 10_000))->toBe(18);
});

it('runs on the testing database, never the dev database', function (): void {
    // Parallel workers append their token: testing_test_1, testing_test_2, ...
    expect(DB::connection()->getDatabaseName())->toMatch('/^testing(_test_\d+)?$/');
});

it('has the postgis and pg_trgm extensions installed', function (): void {
    $extensions = DB::table('pg_extension')->pluck('extname');

    expect($extensions)->toContain('postgis', 'pg_trgm');
});

it('measures the distance between two geography points in meters', function (): void {
    // One degree of longitude on the equator, on the WGS 84 spheroid.
    $meters = DB::scalar(
        "select ST_Distance('SRID=4326;POINT(0 0)'::geography, 'SRID=4326;POINT(1 0)'::geography)",
    );

    expect($meters)->toBeFloat()->toEqualWithDelta(111_319.49, 0.01);
});

it('scores the similarity of Arabic words with trigrams', function (): void {
    $similarity = DB::scalar("select similarity('صيدلية', 'صيدليه')");

    expect($similarity)->toBeFloat()->toBeGreaterThan(0.3);
});
