<?php

declare(strict_types=1);

namespace App\Domains\Requests\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Requests\Models\RequestReply;

/**
 * What a resident may do with a reply to a request through the API. Admins act in Filament, under its
 * own rules (step 1.5).
 */
final class RequestReplyPolicy
{
    /**
     * A reply is between its author and the owner of the request, who sees it but cannot change it:
     * competitors do not see each other's offers.
     */
    public function view(?User $user, RequestReply $reply): bool
    {
        return $reply->user()->is($user)
            || ($reply->request !== null && $reply->request->user()->is($user));
    }

    public function update(User $user, RequestReply $reply): bool
    {
        return $reply->user()->is($user);
    }

    public function delete(User $user, RequestReply $reply): bool
    {
        return $reply->user()->is($user);
    }
}
