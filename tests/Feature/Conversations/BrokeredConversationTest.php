<?php

declare(strict_types=1);

use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

it('keeps the owner of a brokered property out of its conversations (invariant 2)', function (): void {
    $owner = User::factory()->create();
    $property = Property::factory()->for($owner)->brokered()->published()->create();
    $resident = User::factory()->create();
    $staff = User::factory()->create();

    $conversation = Conversation::factory()->about($property)->answeredByThePlatform()->for($resident, 'resident')->create();
    Message::factory()->for($conversation)->for($resident, 'sender')->create(['body' => 'لسه متاحة؟']);
    Message::factory()->for($conversation)->for($staff, 'sender')->create(['body' => 'متاحة، تحب تحدد ميعاد معاينة؟']);

    $stored = DB::table('conversations')->where('id', $conversation->id)->first();
    $everything = json_encode([
        $stored,
        DB::table('messages')->where('conversation_id', $conversation->id)->get(),
        Conversation::query()->with('messages')->findOrFail($conversation->id)->toArray(),
    ]);

    expect($stored?->counterpart_id)->toBeNull()
        ->and($conversation->isAnsweredByThePlatform())->toBeTrue()
        ->and($everything)->not->toContain($owner->id)
        ->and(Gate::forUser($resident)->allows('view', $conversation))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $conversation))->toBeFalse()
        ->and(Gate::forUser($staff)->allows('view', $conversation))->toBeFalse();
});
