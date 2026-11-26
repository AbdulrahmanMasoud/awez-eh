<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Identity\Models\User;

/**
 * For DeleteAccount: the account's picture and its uploads not attached to anything go, with
 * their files. Photos of listings go with the listings, through their own domain's Action.
 */
final readonly class DeleteAccountMedia
{
    public function handle(User $user): void
    {
        $user->clearMediaCollection(User::AVATAR);

        foreach ($user->pendingUploads()->lazyById() as $upload) {
            $upload->delete();
        }
    }
}
