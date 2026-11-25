<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Models\SocialAccount;
use App\Domains\Identity\Models\User;

/**
 * Reached only through the signed-in account's own endpoints (`me`), never by id: no endpoint
 * takes another user's row, so no one is allowed one.
 */
final class SocialAccountPolicy
{
    public function view(User $user, SocialAccount $socialAccount): bool
    {
        return false;
    }

    public function update(User $user, SocialAccount $socialAccount): bool
    {
        return false;
    }

    public function delete(User $user, SocialAccount $socialAccount): bool
    {
        return false;
    }
}
