<?php

declare(strict_types=1);

namespace App\Support\Geo;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * A Point written into a geography(Point, 4326) column.
 *
 * Laravel inlines expressions into the SQL without bindings, so the SQL is built here from the
 * Point's two floats, which are range-checked numbers, never text from a request.
 */
final readonly class PointExpression implements Expression
{
    public function __construct(public Point $point) {}

    public function getValue(Grammar $grammar): string
    {
        // PostGIS points are (x, y): longitude first. %F is a plain decimal in every locale, never
        // `1.0E-5`. 15 decimals give back the exact double for any coordinate of 8 degrees or more
        // (all of Egypt) and are within a nanometre anywhere else.
        return sprintf(
            'ST_SetSRID(ST_MakePoint(%.15F, %.15F), %d)::geography',
            $this->point->longitude,
            $this->point->latitude,
            Point::SRID,
        );
    }
}
