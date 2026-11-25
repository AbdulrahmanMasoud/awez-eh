<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Models;

use App\Domains\Conversations\Enums\ConversationStatus;
use App\Domains\Conversations\Policies\ConversationPolicy;
use App\Domains\Identity\Models\User;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A conversation between a resident and the advertiser or craftsman behind a subject, or the
 * platform's staff.
 */
#[Fillable(['status'])]
#[UseFactory(ConversationFactory::class)]
#[UsePolicy(ConversationPolicy::class)]
final class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * What it is about: a property, a provider, a place, a used item or a reply to a request.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The resident who opened it.
     *
     * @return BelongsTo<User, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    /**
     * The advertiser or craftsman; none when the platform answers.
     *
     * @return BelongsTo<User, $this>
     */
    public function counterpart(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterpart_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The platform's staff answer, as on every brokered property: there is no counterpart, so the
     * owner never appears (invariant 2).
     */
    public function isAnsweredByThePlatform(): bool
    {
        return $this->counterpart_id === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'resident_read_at' => 'datetime',
            'counterpart_read_at' => 'datetime',
        ];
    }
}
