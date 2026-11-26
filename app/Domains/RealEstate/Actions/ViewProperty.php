<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Support\PropertyRelations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Someone opens a property: it counts as a view at most once a day per account, or per IP address
 * for guests, and never when its owner looks at it. The IP is hashed before it names a cache key.
 */
final readonly class ViewProperty
{
    private const int DAY_IN_SECONDS = 86_400;

    public function handle(Property $property, ?User $viewer, string $ip): Property
    {
        if (! $property->isOwnedBy($viewer) && $property->status->isPublic()) {
            $who = $viewer instanceof User ? "user:{$viewer->id}" : 'ip:'.hash('sha256', $ip);
            $key = "property-view:{$property->id}:{$who}:".Date::now()->toDateString();

            // add() writes only if the key is missing, atomically: one count per key and day.
            if (Cache::add($key, true, self::DAY_IN_SECONDS)) {
                // Not an edit: the row's updated_at stays as it is.
                $property->newQuery()->whereKey($property->id)->toBase()->increment('views_count');
            }
        }

        return $property->refresh()->load(PropertyRelations::SHOWN);
    }
}
