<?php

declare(strict_types=1);

namespace App\Filament\Review;

use App\Domains\Identity\Models\User;

/**
 * The kinds المراجعة and the dashboard's محتاج قرارك show, in their tabs' order.
 */
final readonly class ReviewKinds
{
    /**
     * @param  list<ReviewableKind>  $kinds
     */
    public function __construct(private array $kinds) {}

    /**
     * The kinds this admin may review.
     *
     * @return array<string, ReviewableKind>
     */
    public function for(User $admin): array
    {
        $kinds = [];

        foreach ($this->kinds as $kind) {
            if ($kind->canReview($admin)) {
                $kinds[$kind->key()] = $kind;
            }
        }

        return $kinds;
    }

    public function find(User $admin, ?string $key): ?ReviewableKind
    {
        $kinds = $this->for($admin);

        return $kinds[$key ?? ''] ?? (reset($kinds) ?: null);
    }

    /** Everything waiting that this admin may review: the sidebar badge. */
    public function count(User $admin): int
    {
        return array_sum(array_map(fn (ReviewableKind $kind): int => $kind->pending()->count(), $this->for($admin)));
    }
}
