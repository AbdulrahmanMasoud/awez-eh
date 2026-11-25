<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;

/**
 * Users as admins see them, in the panel only. Residents manage their own account through the
 * API (`me`), which takes no user id and asks no policy. Nobody creates, edits or deletes a user
 * here: accounts are the residents' own (task 005).
 */
final class UserPolicy
{
    public function viewAny(User $admin): bool
    {
        return $admin->mayAdminister('ViewAny:User');
    }

    public function view(User $admin, User $user): bool
    {
        return $admin->mayAdminister('View:User');
    }

    public function create(User $admin): bool
    {
        return false;
    }

    public function update(User $admin, User $user): bool
    {
        return false;
    }

    public function delete(User $admin, User $user): bool
    {
        return false;
    }

    public function deleteAny(User $admin): bool
    {
        return false;
    }

    /**
     * An active account other than the admin's own.
     */
    public function suspend(User $admin, User $user): bool
    {
        return $admin->mayAdminister('Suspend:User') && $user->status === UserStatus::Active && ! $admin->is($user);
    }

    public function reactivate(User $admin, User $user): bool
    {
        return $admin->mayAdminister('Reactivate:User') && $user->status === UserStatus::Suspended;
    }

    /**
     * A deleted account keeps no roles worth changing; the last super_admin is the Action's check.
     */
    public function updateRoles(User $admin, User $user): bool
    {
        return $admin->mayAdminister('UpdateRoles:User') && $user->status !== UserStatus::Deleted;
    }

    public function revealPhone(User $admin, User $user): bool
    {
        return $admin->mayAdminister('RevealPhone:User') && $user->phone !== null;
    }
}
