<?php

declare(strict_types=1);

namespace App\Domains\Services\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Services\Models\Provider;

/**
 * What a resident may do with a craftsman's profile through the API. Admins act in Filament, under its
 * own rules (step 1.5).
 */
final class ProviderPolicy
{
    /**
     * Published profiles are public; craftsmen see their own in any status.
     */
    public function view(?User $user, Provider $provider): bool
    {
        return $provider->status->isPublic() || $provider->user()->is($user);
    }

    public function update(User $user, Provider $provider): bool
    {
        return $provider->user()->is($user);
    }

    public function delete(User $user, Provider $provider): bool
    {
        return $provider->user()->is($user);
    }
}
