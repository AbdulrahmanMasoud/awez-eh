<?php

declare(strict_types=1);

namespace App\Domains\Favorites\Policies;

use App\Domains\Favorites\Models\Favorite;
use App\Domains\Identity\Models\User;

/**
 * What a resident may do with a favorite through the API: only its owner.
 */
final class FavoritePolicy
{
    public function view(?User $user, Favorite $favorite): bool
    {
        return $favorite->user()->is($user);
    }

    public function update(User $user, Favorite $favorite): bool
    {
        return $favorite->user()->is($user);
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $favorite->user()->is($user);
    }
}
