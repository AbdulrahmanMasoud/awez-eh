<?php

declare(strict_types=1);

namespace App\Domains\Promotions\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Promotions\Models\FeaturedPlacement;

/**
 * What a resident may do with a featured placement through the API: only the advertiser who bought
 * it.
 */
final class FeaturedPlacementPolicy
{
    public function view(?User $user, FeaturedPlacement $placement): bool
    {
        return $placement->user()->is($user);
    }

    public function update(User $user, FeaturedPlacement $placement): bool
    {
        return $placement->user()->is($user);
    }

    public function delete(User $user, FeaturedPlacement $placement): bool
    {
        return $placement->user()->is($user);
    }
}
