<?php

declare(strict_types=1);

namespace App\Domains\Places\Actions;

use App\Domains\Identity\Models\User;

/**
 * A place is a business, not a person: a deleted account's places lose their owner and stay as
 * they are, for admins to look after or another owner to claim. Called by DeleteAccount.
 */
final readonly class ReleaseAccountPlaces
{
    public function handle(User $user): void
    {
        $user->places()->update(['user_id' => null]);
    }
}
