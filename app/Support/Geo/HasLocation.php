<?php

declare(strict_types=1);

namespace App\Support\Geo;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Near me" for a model with a geography(Point, 4326) `location` column. Both scopes run in
 * PostgreSQL, on the ellipsoid, where the column's GiST index serves ST_DWithin (invariant 5).
 *
 * The SQL is literal and names `location` unqualified: a join with another table that has a
 * `location` column fails loudly as ambiguous, never silently.
 */
trait HasLocation
{
    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withinMeters(Builder $query, Point $from, int $meters): void
    {
        // Longitude first: PostGIS points are (x, y).
        $query->whereRaw(
            'ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
            [$from->longitude, $from->latitude, $meters],
        );
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function orderByDistance(Builder $query, Point $from): void
    {
        $query->orderByRaw(
            'ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
            [$from->longitude, $from->latitude],
        );
    }
}
