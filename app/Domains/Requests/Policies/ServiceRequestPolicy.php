<?php

declare(strict_types=1);

namespace App\Domains\Requests\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Requests\Models\ServiceRequest;

/**
 * What a resident may do with a request through the API. Admins act in Filament, under its
 * own rules (step 1.5).
 */
final class ServiceRequestPolicy
{
    /**
     * Open requests are public; closed and expired ones only to the resident who wrote them.
     */
    public function view(?User $user, ServiceRequest $request): bool
    {
        return $request->isOpen() || $request->user()->is($user);
    }

    public function update(User $user, ServiceRequest $request): bool
    {
        return $request->user()->is($user);
    }

    public function delete(User $user, ServiceRequest $request): bool
    {
        return $request->user()->is($user);
    }
}
