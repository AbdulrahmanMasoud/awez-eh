<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Identity\Support\SuperAdmins;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Suspends an account and signs it out everywhere: its API tokens (push tokens go with them)
 * and its panel sessions. The API then answers `account_suspended`, and the panel refuses it.
 */
final readonly class SuspendUser
{
    public function __construct(private SuperAdmins $superAdmins) {}

    public function handle(User $user, User $admin, string $reason): User
    {
        return DB::transaction(function () use ($user, $admin, $reason): User {
            $this->superAdmins->ensureAnotherRemainsWithout($user);

            $before = $user->status;
            $user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => Date::now()])->save();

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();

            AdminLog::record('suspended', $user, $admin, $reason, ['status' => $before->value], ['status' => UserStatus::Suspended->value]);

            return $user;
        });
    }
}
