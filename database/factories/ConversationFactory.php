<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Conversations\Enums\ConversationStatus;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Conversation>
 */
final class ConversationFactory extends Factory
{
    #[\Override]
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'subject_type' => new Property()->getMorphClass(),
            'subject_id' => Property::factory()->published(),
            'resident_id' => User::factory(),
            // The subject's owner answers, unless the platform does.
            'counterpart_id' => fn (array $attributes): mixed => Property::query()->whereKey($attributes['subject_id'])->value('user_id'),
            'status' => ConversationStatus::Open,
            'last_message_at' => now(),
        ];
    }

    /** About a given subject, answered by its owner. */
    public function about(Model $subject): static
    {
        return $this->state([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'counterpart_id' => $subject->getAttribute('user_id'),
        ]);
    }

    /** Answered by the platform's staff, as on a brokered property: no counterpart. */
    public function answeredByThePlatform(): static
    {
        return $this->state(['counterpart_id' => null]);
    }
}
