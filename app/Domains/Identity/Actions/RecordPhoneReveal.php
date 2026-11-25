<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Support\PhoneNumber;

/**
 * An admin looked at a user's full number: the log says who and when, never the number.
 */
final readonly class RecordPhoneReveal
{
    public function handle(User $user, User $admin): ?PhoneNumber
    {
        AdminLog::record('phone_revealed', $user, $admin);

        return $user->phone;
    }
}
