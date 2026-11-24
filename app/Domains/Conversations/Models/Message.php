<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Models;

use App\Domains\Conversations\Enums\MessageKind;
use App\Domains\Conversations\Policies\MessagePolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Models\Concerns\Reportable;
use App\Support\Casts\PointCast;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message in a conversation: text, a shared location, or a system notice. Images come with media
 * (step 2.1).
 */
#[Fillable(['kind', 'body', 'location'])]
#[UseFactory(MessageFactory::class)]
#[UsePolicy(MessagePolicy::class)]
final class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    use HasUuids;
    use Reportable;

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A participant, or a member of the platform's staff.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MessageKind::class,
            'location' => PointCast::class,
        ];
    }
}
