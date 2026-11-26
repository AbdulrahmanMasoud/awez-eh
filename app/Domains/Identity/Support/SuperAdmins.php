<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\LastSuperAdmin;
use App\Domains\Identity\Models\User;

/**
 * Keeps at least one active super_admin, who alone manages roles.
 */
final class SuperAdmins
{
    /**
     * @throws LastSuperAdmin
     */
    public function ensureAnotherRemainsWithout(User $user): void
    {
        if (! $user->hasRole(AdminRole::SuperAdmin->value)) {
            return;
        }

        // The rows are locked, so two admins removing each other at once cannot both succeed;
        // PostgreSQL locks rows, not a count.
        $others = User::role(AdminRole::SuperAdmin->value)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($user->getKey())
            ->lockForUpdate()
            ->pluck('id');

        if ($others->isEmpty()) {
            throw new LastSuperAdmin;
        }
    }
}
