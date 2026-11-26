<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Identity\Models\User;

/**
 * Deletes the uploaded picture and its files; the Google or Apple picture shows again.
 */
final readonly class RemoveAvatar
{
    public function handle(User $user): User
    {
        $user->clearMediaCollection(User::AVATAR);

        return $user->unsetRelation('media');
    }
}
