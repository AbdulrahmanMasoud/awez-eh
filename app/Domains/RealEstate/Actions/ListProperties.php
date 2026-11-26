<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\RealEstate\Data\PropertyFilters;
use App\Domains\RealEstate\Enums\PropertySort;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Support\PropertyRelations;
use App\Support\Geo\Point;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * The public list: published properties only, filtered and ordered as asked, a cursor page at a
 * time. Every filter and order has a partial index on published rows (DECISIONS 107); distances
 * are computed by PostGIS (invariant 5).
 */
final readonly class ListProperties
{
    public const int PAGE_SIZE = 20;

    /**
     * @return CursorPaginator<int, Property>
     */
    public function handle(PropertyFilters $filters): CursorPaginator
    {
        $query = $this->filtered($filters);

        $query = match ($filters->sort) {
            PropertySort::Newest => $query->orderByDesc('id'),
            PropertySort::PriceAsc => $query->orderBy('price')->orderBy('id'),
            PropertySort::PriceDesc => $query->orderByDesc('price')->orderByDesc('id'),
            PropertySort::Nearest => $this->nearest($query, $filters->near ?? throw new InvalidArgumentException('Nearest needs a location.')),
        };

        return $query->with(PropertyRelations::SHOWN)->cursorPaginate(self::PAGE_SIZE);
    }

    /**
     * @return Builder<Property>
     */
    private function filtered(PropertyFilters $filters): Builder
    {
        return Property::query()
            ->published()
            ->when($filters->purpose, fn (Builder $query) => $query->where('purpose', $filters->purpose))
            ->when($filters->types !== [], fn (Builder $query) => $query->whereIn('type', $filters->types))
            ->when($filters->districtIds !== [], fn (Builder $query) => $query->whereIn('district_id', $filters->districtIds))
            ->when($filters->priceMin, fn (Builder $query) => $query->where('price', '>=', $filters->priceMin?->getMinorAmount()->toInt()))
            ->when($filters->priceMax, fn (Builder $query) => $query->where('price', '<=', $filters->priceMax?->getMinorAmount()->toInt()))
            ->when($filters->areaMin !== null, fn (Builder $query) => $query->where('area_m2', '>=', $filters->areaMin))
            ->when($filters->areaMax !== null, fn (Builder $query) => $query->where('area_m2', '<=', $filters->areaMax))
            ->when($filters->bedroomsMin !== null, fn (Builder $query) => $query->where('bedrooms', '>=', $filters->bedroomsMin))
            ->when($filters->furnished !== null, fn (Builder $query) => $query->where('furnished', $filters->furnished))
            ->when($filters->finishing, fn (Builder $query) => $query->where('finishing', $filters->finishing))
            ->when($filters->brokered !== null, fn (Builder $query) => $query->where('brokered', $filters->brokered))
            ->when($filters->near, fn (Builder $query, Point $near) => $query->withinMeters($near, $filters->radiusMeters ?? 0));
    }

    /**
     * Ordered by distance, in whole metres, then by id. The distance is a column of a subquery, so
     * the cursor can compare it as it compares any column.
     *
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    private function nearest(Builder $query, Point $from): Builder
    {
        $measured = $query
            ->select('properties.*')
            ->selectRaw('round(ST_Distance(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography))::bigint as distance_m', [$from->longitude, $from->latitude]);

        return Property::query()->fromSub($measured, 'properties')->orderBy('distance_m')->orderBy('id');
    }
}
