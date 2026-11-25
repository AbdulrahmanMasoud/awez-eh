<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ReportStatus;

/**
 * A deleted account's open reports are withdrawn; resolved and dismissed ones stay, as the
 * record of what admins decided. Called by DeleteAccount.
 */
final readonly class WithdrawAccountReports
{
    public function handle(User $user): void
    {
        $user->reports()->where('status', ReportStatus::Open)->delete();
    }
}
