<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Models\Concerns;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Content an admin reviews before the public sees it (invariant 9): the moderation columns,
 * the reviewer, and the `published()` scope. Status changes are Actions, never direct updates.
 *
 * @phpstan-require-extends Model
 */
trait Moderated
{
    /**
     * The column's default, so a new model knows its status before it is saved and read back.
     * The model casts `status`, `reviewed_at` and `published_at` in its own casts().
     */
    protected function initializeModerated(): void
    {
        $this->attributes['status'] ??= ContentStatus::PendingReview->value;
    }

    /**
     * The admin who approved or rejected it.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ContentStatus::Published);
    }
}
