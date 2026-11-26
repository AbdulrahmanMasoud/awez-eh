<?php

declare(strict_types=1);

namespace App\Domains\Services\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Services\Models\Trade;

/**
 * Reference data, changed only in the admin panel by a role allowed to (`data_entry` and
 * `super_admin` by default). Rows are deactivated, never deleted (DECISIONS 42). How residents
 * read reference data through the API is decided with its endpoints in phase 2.
 */
final class TradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mayAdminister('ViewAny:Trade');
    }

    public function view(User $user, Trade $trade): bool
    {
        return $user->mayAdminister('View:Trade');
    }

    public function create(User $user): bool
    {
        return $user->mayAdminister('Create:Trade');
    }

    public function update(User $user, Trade $trade): bool
    {
        return $user->mayAdminister('Update:Trade');
    }

    public function reorder(User $user): bool
    {
        return $user->mayAdminister('Reorder:Trade');
    }

    public function delete(User $user, Trade $trade): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
