<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;

/**
 * A deleted account's used items go, except those something points at (a conversation, a
 * favorite, a placement, a report), which are archived. Called by DeleteAccount.
 */
final readonly class RemoveAccountUsedItems
{
    public function handle(User $user): void
    {
        foreach ($user->usedItems()->lazyById() as $item) {
            if ($item->isPointedAt()) {
                $item->forceFill(['status' => ContentStatus::Archived])->save();

                continue;
            }

            $item->delete();
        }
    }
}
