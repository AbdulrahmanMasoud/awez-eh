<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

/**
 * The platform's own account (config `awez.platform_account.email`): it owns brokered properties
 * whose owners have no account. Created once; running again returns the same account. It cannot
 * sign in: no password, no Google or Apple account, no phone.
 */
final readonly class EnsurePlatformAccount
{
    /**
     * @return array{User, bool} The account, and whether it was created now.
     */
    public function handle(): array
    {
        $email = Config::string('awez.platform_account.email');
        $existing = User::query()->where('email', $email)->first();

        if ($existing instanceof User) {
            return [$existing, false];
        }

        $account = new User(['name' => (string) __('real_estate.platform_name', [], 'ar')]);
        $account->forceFill(['email' => $email, 'email_verified_at' => Date::now()])->save();

        return [$account->refresh(), true];
    }

    public static function find(): ?User
    {
        return User::query()->where('email', Config::string('awez.platform_account.email'))->first();
    }
}
