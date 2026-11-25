<?php

declare(strict_types=1);

namespace App\Domains\Reviews\Models;

use App\Domains\Bookings\Models\Booking;
use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Models\Concerns\Reportable;
use App\Domains\Reviews\Policies\ReviewPolicy;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A rating from 1 to 5 after a booking, one per booking. That the booking was completed is checked
 * by the Action in step 3.2.
 */
#[Fillable(['rating', 'comment'])]
#[UseFactory(ReviewFactory::class)]
#[UsePolicy(ReviewPolicy::class)]
final class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    use HasUuids;
    use Reportable;

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * The craftsman or the property reviewed.
     *
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The admin who hid it.
     *
     * @return BelongsTo<User, $this>
     */
    public function hider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'replied_at' => 'datetime',
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }
}
