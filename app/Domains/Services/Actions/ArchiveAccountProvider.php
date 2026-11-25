<?php

declare(strict_types=1);

namespace App\Domains\Services\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;

/**
 * A deleted account's craftsman profile is archived, never deleted: bookings and reviews point
 * at it. Called by DeleteAccount.
 */
final readonly class ArchiveAccountProvider
{
    public function handle(User $user): void
    {
        $user->provider?->forceFill(['status' => ContentStatus::Archived])->save();
    }
}
