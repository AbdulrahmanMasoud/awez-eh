<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Identity\Models\User;

/**
 * The account's own picture, replacing the previous one (a single-file collection). Until its
 * sizes exist, the account shows the Google or Apple picture.
 */
final readonly class SetAvatar
{
    public function __construct(private AddImage $addImage) {}

    public function handle(User $user, string $path): User
    {
        $this->addImage->handle($user, User::AVATAR, $path);

        return $user->unsetRelation('media');
    }
}
