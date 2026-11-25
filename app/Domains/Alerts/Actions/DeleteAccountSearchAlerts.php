<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Actions;

use App\Domains\Identity\Models\User;

/**
 * Called by DeleteAccount.
 */
final readonly class DeleteAccountSearchAlerts
{
    public function handle(User $user): void
    {
        $user->searchAlerts()->delete();
    }
}
