<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Support\PropertyRelations;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * A resident's own properties, in every status, newest first.
 */
final readonly class ListOwnProperties
{
    /**
     * @return CursorPaginator<int, Property>
     */
    public function handle(User $owner): CursorPaginator
    {
        return $owner->properties()
            ->with(PropertyRelations::SHOWN)
            ->orderByDesc('id')
            ->cursorPaginate(ListProperties::PAGE_SIZE);
    }
}
