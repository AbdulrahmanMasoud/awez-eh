<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;

/**
 * What a resident may do with a used item through the API. Admins act in Filament, under its
 * own rules (step 1.5).
 */
final class UsedItemPolicy
{
    /**
     * Published items are public; sellers see their own in any status.
     */
    public function view(?User $user, UsedItem $usedItem): bool
    {
        return $usedItem->status->isPublic() || $usedItem->user()->is($user);
    }

    public function update(User $user, UsedItem $usedItem): bool
    {
        return $usedItem->user()->is($user);
    }

    public function delete(User $user, UsedItem $usedItem): bool
    {
        return $usedItem->user()->is($user);
    }
}
