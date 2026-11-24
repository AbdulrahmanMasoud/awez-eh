<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Commissions\Enums\LeadChannel;
use App\Domains\Commissions\Models\Lead;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A lead from a resident's conversation about a brokered property, which the platform answers.
 *
 * @extends Factory<Lead>
 */
final class LeadFactory extends Factory
{
    #[\Override]
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory()->brokered()->published(),
            'resident_id' => User::factory(),
            'channel' => LeadChannel::Chat,
            'source_type' => new Conversation()->getMorphClass(),
            'source_id' => fn (array $attributes): mixed => Conversation::factory()->answeredByThePlatform()->create([
                'subject_type' => new Property()->getMorphClass(),
                'subject_id' => $attributes['property_id'],
                'resident_id' => $attributes['resident_id'],
            ])->getKey(),
            'voids_lead_id' => null,
            'void_reason' => null,
        ];
    }

    /** A correction: a new row, with the voided lead's details, that voids it. */
    public function voiding(Lead $lead): static
    {
        return $this->state([
            'property_id' => $lead->property_id,
            'resident_id' => $lead->resident_id,
            'channel' => $lead->channel,
            'source_type' => $lead->source_type,
            'source_id' => $lead->source_id,
            'voids_lead_id' => $lead->id,
            'void_reason' => 'سُجل بالخطأ.',
        ]);
    }
}
