<?php

declare(strict_types=1);

use App\Domains\Commissions\Enums\LeadChannel;
use App\Domains\Commissions\Models\Lead;
use App\Domains\Conversations\Models\Conversation;

it('records a lead from the conversation that created it', function (): void {
    $lead = Lead::factory()->create();

    expect($lead->channel)->toBe(LeadChannel::Chat)
        ->and($lead->source)->toBeInstanceOf(Conversation::class)
        ->and($lead->source?->getAttribute('subject_id'))->toBe($lead->property_id)
        ->and($lead->property?->brokered)->toBeTrue();
});

it('corrects a lead with a new row that voids it, keeping both', function (): void {
    $lead = Lead::factory()->create();

    $correction = Lead::factory()->voiding($lead)->create(['void_reason' => 'الساكن فتح المحادثة بالغلط']);

    expect($correction->voidedLead?->is($lead))->toBeTrue()
        ->and($lead->voidingLead?->is($correction))->toBeTrue()
        ->and($correction->source?->is($lead->source))->toBeTrue()
        ->and(Lead::query()->count())->toBe(2);
});
