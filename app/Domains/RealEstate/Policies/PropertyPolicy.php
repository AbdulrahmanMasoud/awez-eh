<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;

/**
 * What a resident may do with a property through the API. Admins act in Filament, under its
 * own rules (step 1.5).
 */
final class PropertyPolicy
{
    /**
     * Published listings are public; owners see their own in any status.
     */
    public function view(?User $user, Property $property): bool
    {
        return $property->status->isPublic() || $property->user()->is($user);
    }

    public function update(User $user, Property $property): bool
    {
        return $property->user()->is($user);
    }

    public function delete(User $user, Property $property): bool
    {
        return $property->user()->is($user);
    }
}
