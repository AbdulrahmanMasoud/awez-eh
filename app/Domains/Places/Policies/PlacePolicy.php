<?php

declare(strict_types=1);

namespace App\Domains\Places\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Places\Models\Place;

/**
 * What a resident may do with a place through the API. Admins act in Filament, under its
 * own rules (step 1.5).
 */
final class PlacePolicy
{
    /**
     * Published places are public; whoever claimed a place sees it in any status. An unclaimed place has no
     * owner, so only admins change it.
     */
    public function view(?User $user, Place $place): bool
    {
        return $place->status->isPublic() || $place->user()->is($user);
    }

    public function update(User $user, Place $place): bool
    {
        return $place->user()->is($user);
    }

    public function delete(User $user, Place $place): bool
    {
        return $place->user()->is($user);
    }
}
