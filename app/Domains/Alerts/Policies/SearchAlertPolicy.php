<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Policies;

use App\Domains\Alerts\Models\SearchAlert;
use App\Domains\Identity\Models\User;

/**
 * What a resident may do with a search alert through the API: only its owner.
 */
final class SearchAlertPolicy
{
    public function view(?User $user, SearchAlert $alert): bool
    {
        return $alert->user()->is($user);
    }

    public function update(User $user, SearchAlert $alert): bool
    {
        return $alert->user()->is($user);
    }

    public function delete(User $user, SearchAlert $alert): bool
    {
        return $alert->user()->is($user);
    }
}
