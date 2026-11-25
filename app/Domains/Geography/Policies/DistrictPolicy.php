<?php

declare(strict_types=1);

namespace App\Domains\Geography\Policies;

use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;

/**
 * Reference data, changed only in the admin panel by a role allowed to (`data_entry` and
 * `super_admin` by default). Rows are deactivated, never deleted (DECISIONS 42). How residents
 * read reference data through the API is decided with its endpoints in phase 2.
 */
final class DistrictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mayAdminister('ViewAny:District');
    }

    public function view(User $user, District $district): bool
    {
        return $user->mayAdminister('View:District');
    }

    public function create(User $user): bool
    {
        return $user->mayAdminister('Create:District');
    }

    public function update(User $user, District $district): bool
    {
        return $user->mayAdminister('Update:District');
    }

    public function reorder(User $user): bool
    {
        return $user->mayAdminister('Reorder:District');
    }

    public function delete(User $user, District $district): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
