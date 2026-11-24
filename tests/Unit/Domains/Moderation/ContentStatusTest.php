<?php

declare(strict_types=1);

use App\Domains\Moderation\Enums\ContentStatus;

it('makes only published content public', function (): void {
    $public = array_filter(ContentStatus::cases(), fn (ContentStatus $status): bool => $status->isPublic());

    expect(array_values($public))->toBe([ContentStatus::Published]);
});

it('stores the statuses ARCHITECTURE §5 names, in snake case', function (): void {
    expect(array_column(ContentStatus::cases(), 'value'))
        ->toBe(['draft', 'pending_review', 'published', 'paused', 'sold', 'rented', 'rejected', 'archived']);
});
