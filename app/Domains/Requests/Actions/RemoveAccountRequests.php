<?php

declare(strict_types=1);

namespace App\Domains\Requests\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Requests\Enums\RequestStatus;

/**
 * A deleted account's requests go, except those with replies: the residents who replied keep
 * them, closed. Replies the account wrote to others' requests stay, like its messages, under the
 * deleted account's name. Called by DeleteAccount.
 */
final readonly class RemoveAccountRequests
{
    public function handle(User $user): void
    {
        $user->serviceRequests()->whereDoesntHave('replies')->delete();

        $user->serviceRequests()->where('status', RequestStatus::Open)->update(['status' => RequestStatus::Closed]);
    }
}
