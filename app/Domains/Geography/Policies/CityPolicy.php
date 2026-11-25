<?php

declare(strict_types=1);

namespace App\Domains\Geography\Policies;

use App\Domains\Geography\Models\City;
use App\Domains\Identity\Models\User;

/**
 * Reference data, changed only in the admin panel by a role allowed to (`data_entry` and
 * `super_admin` by default). Rows are deactivated, never deleted (DECISIONS 42). How residents
 * read reference data through the API is decided with its endpoints in phase 2.
 */
final class CityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mayAdminister('ViewAny:City');
    }

    public function view(User $user, City $city): bool
    {
        return $user->mayAdminister('View:City');
    }

    public function create(User $user): bool
    {
        return $user->mayAdminister('Create:City');
    }

    public function update(User $user, City $city): bool
    {
        return $user->mayAdminister('Update:City');
    }

    public function reorder(User $user): bool
    {
        return $user->mayAdminister('Reorder:City');
    }

    public function delete(User $user, City $city): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
