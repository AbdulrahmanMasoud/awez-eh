<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;

/**
 * A deleted account's properties (DECISIONS 41). Brokered ones are archived and kept, with their
 * agreements and leads: the platform's commission rests on them. The others are deleted, unless
 * something points at them (a conversation, a booking, a favorite, a placement, a report, an
 * agreement, a lead), which keeps them archived instead of leaving that row pointing at nothing.
 * Called by DeleteAccount, inside its transaction.
 */
final readonly class RemoveAccountProperties
{
    public function handle(User $user): void
    {
        foreach ($user->properties()->lazyById() as $property) {
            if ($property->brokered || $property->isPointedAt()) {
                $property->forceFill(['status' => ContentStatus::Archived])->save();

                continue;
            }

            $property->delete();
        }
    }
}
