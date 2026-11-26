<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Support;

use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\Moderation\Enums\ContentActor;
use App\Domains\Moderation\Enums\ContentDeal;
use App\Domains\Moderation\Enums\ContentStatus;

/**
 * The lifecycle of moderated content (ARCHITECTURE §6): properties, providers, places and used
 * items. Pure: each domain's own Actions ask it before they change their rows.
 *
 * Every move not written here is refused. Sold, rented and archived are terminal.
 */
final class ContentStatusMachine
{
    /**
     * Where the action takes the content, or null when this actor may not take it from there.
     */
    public function next(ContentState $state, ContentAction $action, ContentActor $actor): ?ContentStatus
    {
        if ($actor !== $this->actorOf($action)) {
            return null;
        }

        $from = $state->status;

        return match ($action) {
            // A rejected listing goes back to review only once its owner changed something.
            ContentAction::Submit => $from === ContentStatus::Draft || ($from === ContentStatus::Rejected && $state->changedSinceReview) ? ContentStatus::PendingReview : null,
            ContentAction::Approve => $from === ContentStatus::PendingReview ? ContentStatus::Published : null,
            ContentAction::Reject => $from === ContentStatus::PendingReview ? ContentStatus::Rejected : null,
            ContentAction::TakeDown => $from === ContentStatus::Published ? ContentStatus::Rejected : null,
            ContentAction::Pause => $from === ContentStatus::Published ? ContentStatus::Paused : null,
            // Edited while paused: reviewed again before the public sees the change.
            ContentAction::Resume => $from === ContentStatus::Paused ? ($state->changedSinceReview ? ContentStatus::PendingReview : ContentStatus::Published) : null,
            ContentAction::MarkSold => $state->deal === ContentDeal::Sale && $this->isLive($from) ? ContentStatus::Sold : null,
            ContentAction::MarkRented => $state->deal === ContentDeal::Rent && $this->isLive($from) ? ContentStatus::Rented : null,
            ContentAction::Archive => $this->isTerminal($from) ? null : ContentStatus::Archived,
        };
    }

    public function allows(ContentState $state, ContentAction $action, ContentActor $actor): bool
    {
        return $this->next($state, $action, $actor) instanceof ContentStatus;
    }

    /**
     * The actions this actor may take now, in ContentAction's order.
     *
     * @return list<ContentAction>
     */
    public function allowedActions(ContentState $state, ContentActor $actor): array
    {
        return array_values(array_filter(
            ContentAction::cases(),
            fn (ContentAction $action): bool => $this->allows($state, $action, $actor),
        ));
    }

    public function actorOf(ContentAction $action): ContentActor
    {
        return match ($action) {
            ContentAction::Approve, ContentAction::Reject, ContentAction::TakeDown => ContentActor::Moderator,
            ContentAction::Submit, ContentAction::Pause, ContentAction::Resume, ContentAction::MarkSold, ContentAction::MarkRented, ContentAction::Archive => ContentActor::Owner,
        };
    }

    /**
     * The owner is told why, so a rejection and a take-down always carry a reason.
     */
    public function requiresReason(ContentAction $action): bool
    {
        return $action === ContentAction::Reject || $action === ContentAction::TakeDown;
    }

    public function isTerminal(ContentStatus $status): bool
    {
        return match ($status) {
            ContentStatus::Sold, ContentStatus::Rented, ContentStatus::Archived => true,
            ContentStatus::Draft, ContentStatus::PendingReview, ContentStatus::Published, ContentStatus::Paused, ContentStatus::Rejected => false,
        };
    }

    public function isEditable(ContentStatus $status): bool
    {
        return ! $this->isTerminal($status);
    }

    /**
     * The status after the owner edits it, or null when it can no longer be edited. A change that
     * needs review takes published content off the public lists until a moderator approves it.
     */
    public function statusAfterEdit(ContentStatus $status, bool $needsReview): ?ContentStatus
    {
        if ($this->isTerminal($status)) {
            return null;
        }

        return $status === ContentStatus::Published && $needsReview ? ContentStatus::PendingReview : $status;
    }

    /**
     * Whether the edit counts as a change since the last review: one that needs review while
     * paused (resume then goes through review), and any edit while rejected (submit is allowed again).
     */
    public function marksChangeAfterEdit(ContentStatus $status, bool $needsReview): bool
    {
        return match ($status) {
            ContentStatus::Paused => $needsReview,
            ContentStatus::Rejected => true,
            default => false,
        };
    }

    private function isLive(ContentStatus $status): bool
    {
        return $status === ContentStatus::Published || $status === ContentStatus::Paused;
    }
}
