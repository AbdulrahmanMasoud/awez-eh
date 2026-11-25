<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\ProfileChanges;
use App\Domains\Identity\Models\User;

final readonly class UpdateProfile
{
    public function handle(User $user, ProfileChanges $changes): User
    {
        $user->fill($changes->attributes())->save();

        return $user;
    }
}
