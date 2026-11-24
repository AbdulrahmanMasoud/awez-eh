<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Enums;

/**
 * The lifecycle of moderated content (ARCHITECTURE §5): properties, providers, places and used
 * items. New content starts as PendingReview (invariant 9); transitions are Actions.
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Paused = 'paused';
    case Sold = 'sold';
    case Rented = 'rented';
    case Rejected = 'rejected';
    case Archived = 'archived';

    /**
     * Whether the public API shows content in this status. Only Published: sold and rented
     * listings leave the public lists, and their owners still see them.
     */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
