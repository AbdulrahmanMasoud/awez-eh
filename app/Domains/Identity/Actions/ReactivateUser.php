<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use Illuminate\Support\Facades\DB;

/**
 * Lifts a suspension. The account signs in again from scratch: its old tokens are gone.
 */
final readonly class ReactivateUser
{
    public function handle(User $user, User $admin, string $reason): User
    {
        return DB::transaction(function () use ($user, $admin, $reason): User {
            $before = $user->status;
            $user->forceFill(['status' => UserStatus::Active, 'suspended_at' => null])->save();

            AdminLog::record('reactivated', $user, $admin, $reason, ['status' => $before->value], ['status' => UserStatus::Active->value]);

            return $user;
        });
    }
}
