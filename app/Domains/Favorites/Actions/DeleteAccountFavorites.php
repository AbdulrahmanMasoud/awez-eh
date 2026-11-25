<?php

declare(strict_types=1);

namespace App\Domains\Favorites\Actions;

use App\Domains\Identity\Models\User;

/**
 * Called by DeleteAccount.
 */
final readonly class DeleteAccountFavorites
{
    public function handle(User $user): void
    {
        $user->favorites()->delete();
    }
}
