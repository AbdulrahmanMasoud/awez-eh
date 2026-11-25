<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Policies;

use App\Domains\Commissions\Models\Lead;
use App\Domains\Identity\Models\User;

/**
 * Leads are the platform's records (invariant 2): no resident, not even the one a lead is about,
 * reads or changes one through the API. Admins see them in Filament, under its own rules.
 */
final class LeadPolicy
{
    public function view(?User $user, Lead $lead): bool
    {
        return false;
    }

    public function update(User $user, Lead $lead): bool
    {
        return false;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return false;
    }
}
