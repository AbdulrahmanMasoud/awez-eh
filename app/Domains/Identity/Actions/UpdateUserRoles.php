<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Identity\Support\SuperAdmins;
use Illuminate\Support\Facades\DB;

/**
 * Sets a user's admin roles. The last active super_admin keeps the role.
 */
final readonly class UpdateUserRoles
{
    public function __construct(private SuperAdmins $superAdmins) {}

    /**
     * @param  list<AdminRole>  $roles
     */
    public function handle(User $user, array $roles, User $admin, ?string $reason = null): User
    {
        return DB::transaction(function () use ($user, $roles, $admin, $reason): User {
            $before = $this->roleNames($user);
            $after = array_values(array_unique(array_map(fn (AdminRole $role): string => $role->value, $roles)));
            sort($after);

            if (in_array(AdminRole::SuperAdmin->value, $before, true) && ! in_array(AdminRole::SuperAdmin->value, $after, true)) {
                $this->superAdmins->ensureAnotherRemainsWithout($user);
            }

            $user->syncRoles($after);

            if ($before !== $after) {
                AdminLog::record('roles_changed', $user, $admin, $reason, ['roles' => $before], ['roles' => $after]);
            }

            return $user;
        });
    }

    /**
     * @return list<string>
     */
    private function roleNames(User $user): array
    {
        $names = array_filter($user->roles()->pluck('name')->all(), is_string(...));
        sort($names);

        return $names;
    }
}
