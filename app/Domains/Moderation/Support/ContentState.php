<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Support;

use App\Domains\Moderation\Enums\ContentDeal;
use App\Domains\Moderation\Enums\ContentStatus;

/**
 * What ContentStatusMachine needs to know of a row: its status, its deal (null when it cannot be
 * sold or rented), and whether its owner changed it since its last review.
 */
final readonly class ContentState
{
    public function __construct(
        public ContentStatus $status,
        public ?ContentDeal $deal,
        public bool $changedSinceReview,
    ) {}
}
