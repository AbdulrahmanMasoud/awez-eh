<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Policies;

use App\Domains\Conversations\Models\Conversation;
use App\Domains\Identity\Models\User;

/**
 * What a resident may do with a conversation through the API: only its two participants see it. One
 * the platform answers has no counterpart, so only its resident sees it; staff read it in Filament.
 */
final class ConversationPolicy
{
    public function view(?User $user, Conversation $conversation): bool
    {
        return $conversation->resident()->is($user) || $conversation->counterpart()->is($user);
    }

    /**
     * Marking it read, closing or blocking it.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->resident()->is($user) || $conversation->counterpart()->is($user);
    }

    /**
     * Nobody deletes a conversation through the API: a lead may point at it.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        return false;
    }
}
