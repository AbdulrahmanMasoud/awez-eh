<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Policies;

use App\Domains\Conversations\Models\Message;
use App\Domains\Identity\Models\User;

/**
 * What a resident may do with a message through the API: the conversation's participants see it,
 * and only its sender changes it.
 */
final class MessagePolicy
{
    public function view(?User $user, Message $message): bool
    {
        return $message->conversation !== null && ($message->conversation->resident()->is($user) || $message->conversation->counterpart()->is($user));
    }

    public function update(User $user, Message $message): bool
    {
        return $message->sender()->is($user);
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->sender()->is($user);
    }
}
