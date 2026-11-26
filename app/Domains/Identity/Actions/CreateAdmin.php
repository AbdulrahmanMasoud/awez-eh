<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\AccountNotActive;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an admin, or promotes the existing account with that email, setting its password and
 * adding the role. The way the first super_admin comes to exist (`awez:create-admin`).
 */
final readonly class CreateAdmin
{
    public function __construct(private SyncAdminRoles $syncAdminRoles) {}

    /**
     * @return array{User, bool} The admin, and whether the account is new.
     *
     * @throws AccountNotActive
     */
    public function handle(string $name, string $email, #[\SensitiveParameter] string $password, AdminRole $role): array
    {
        $this->syncAdminRoles->handle();

        return DB::transaction(function () use ($name, $email, $password, $role): array {
            $user = User::query()->where('email', Str::lower($email))->first();
            $isNew = ! $user instanceof User;

            if ($user instanceof User && $user->status !== UserStatus::Active) {
                throw new AccountNotActive;
            }

            $user ??= new User;
            $user->forceFill([
                'name' => $isNew ? $name : $user->name,
                'email' => Str::lower($email),
                'email_verified_at' => $user->email_verified_at ?? Date::now(),
                // Hashed by the model's cast.
                'password' => $password,
            ])->save();

            $user->assignRole($role->value);

            AdminLog::record($isNew ? 'admin_created' : 'admin_promoted', $user, null, after: ['roles' => [$role->value]]);

            return [$user, $isNew];
        });
    }
}
