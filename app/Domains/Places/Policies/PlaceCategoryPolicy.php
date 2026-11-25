<?php

declare(strict_types=1);

namespace App\Domains\Places\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Places\Models\PlaceCategory;

/**
 * Reference data, changed only in the admin panel by a role allowed to (`data_entry` and
 * `super_admin` by default). Rows are deactivated, never deleted (DECISIONS 42). How residents
 * read reference data through the API is decided with its endpoints in phase 2.
 */
final class PlaceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mayAdminister('ViewAny:PlaceCategory');
    }

    public function view(User $user, PlaceCategory $placeCategory): bool
    {
        return $user->mayAdminister('View:PlaceCategory');
    }

    public function create(User $user): bool
    {
        return $user->mayAdminister('Create:PlaceCategory');
    }

    public function update(User $user, PlaceCategory $placeCategory): bool
    {
        return $user->mayAdminister('Update:PlaceCategory');
    }

    public function reorder(User $user): bool
    {
        return $user->mayAdminister('Reorder:PlaceCategory');
    }

    public function delete(User $user, PlaceCategory $placeCategory): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
