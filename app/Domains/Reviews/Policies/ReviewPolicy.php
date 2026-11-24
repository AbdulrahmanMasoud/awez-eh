<?php

declare(strict_types=1);

namespace App\Domains\Reviews\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Reviews\Models\Review;

/**
 * What a resident may do with a review through the API: everyone sees it unless an admin hid it,
 * the reviewer always sees it, and only the reviewer changes it.
 */
final class ReviewPolicy
{
    public function view(?User $user, Review $review): bool
    {
        return ! $review->is_hidden || $review->reviewer()->is($user);
    }

    public function update(User $user, Review $review): bool
    {
        return $review->reviewer()->is($user);
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->reviewer()->is($user);
    }
}
