<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Conversations\Enums\MessageKind;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use Database\Factories\Support\Random;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
final class MessageFactory extends Factory
{
    #[\Override]
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            // The resident who opened the conversation writes first.
            'sender_id' => fn (array $attributes): mixed => Conversation::query()->whereKey($attributes['conversation_id'])->value('resident_id'),
            'kind' => MessageKind::Text,
            'body' => Random::element(fake(), [
                'السلام عليكم، لسه متاح؟',
                'ممكن أعرف السعر النهائي؟',
                'إمتى أقدر أعاين؟',
                'تمام، هستنى ردك.',
            ]),
            'location' => null,
        ];
    }
}
