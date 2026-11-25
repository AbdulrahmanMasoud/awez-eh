<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use Spatie\Permission\Models\Role;

/**
 * The roles screen (Filament Shield's), for `super_admin` by default. The four admin roles are
 * what the code relies on, so they cannot be deleted; a role added on the screen can.
 */
final class RolePolicy
{
    public function viewAny(User $admin): bool
    {
        return $admin->mayAdminister('ViewAny:Role');
    }

    public function view(User $admin, Role $role): bool
    {
        return $admin->mayAdminister('View:Role');
    }

    public function create(User $admin): bool
    {
        return $admin->mayAdminister('Create:Role');
    }

    public function update(User $admin, Role $role): bool
    {
        return $admin->mayAdminister('Update:Role');
    }

    public function delete(User $admin, Role $role): bool
    {
        return $admin->mayAdminister('Delete:Role') && AdminRole::tryFrom($role->name) === null;
    }

    public function deleteAny(User $admin): bool
    {
        return false;
    }
}
