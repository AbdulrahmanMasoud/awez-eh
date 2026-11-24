<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Models\Report;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Provider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

// Ownership proven at the policy level (invariant 1, definition of done 6). The API-level proof
// comes with each endpoint in phase 2.

dataset('rows only their owner may see', [
    'a property waiting for review' => fn (User $owner): Model => Property::factory()->for($owner)->create(),
    'a rejected property' => fn (User $owner): Model => Property::factory()->for($owner)->rejected()->create(),
    'a provider profile waiting for review' => fn (User $owner): Model => Provider::factory()->for($owner)->create(),
    'a place waiting for review' => fn (User $owner): Model => Place::factory()->for($owner)->create(),
    'a paused used item' => fn (User $owner): Model => UsedItem::factory()->for($owner)->paused()->create(),
    'a closed request' => fn (User $owner): Model => ServiceRequest::factory()->for($owner)->closed()->create(),
    'an open request past its expiry' => fn (User $owner): Model => ServiceRequest::factory()->for($owner)->create(['expires_at' => now()->subMinute()]),
    'a reply to someone else\'s open request' => fn (User $owner): Model => RequestReply::factory()->for($owner)->create(),
    'a report' => fn (User $owner): Model => Report::factory()->for($owner)->create(),
]);

dataset('rows anyone may see', [
    'a published property' => fn (User $owner): Model => Property::factory()->for($owner)->published()->create(),
    'a published provider profile' => fn (User $owner): Model => Provider::factory()->for($owner)->published()->create(),
    'a published place' => fn (User $owner): Model => Place::factory()->for($owner)->published()->create(),
    'a published used item' => fn (User $owner): Model => UsedItem::factory()->for($owner)->published()->create(),
    'an open request' => fn (User $owner): Model => ServiceRequest::factory()->for($owner)->create(),
]);

it('lets the owner view, update and delete their own row in any status', function (Closure $create): void {
    $owner = User::factory()->create();
    $row = $create($owner);

    expect(Gate::forUser($owner)->allows('view', $row))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $row))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $row))->toBeTrue();
})->with('rows only their owner may see');

it('keeps another resident and guests from viewing, updating or deleting it', function (Closure $create): void {
    $row = $create(User::factory()->create());
    $other = User::factory()->create();

    expect(Gate::forUser($other)->allows('view', $row))->toBeFalse()
        ->and(Gate::forUser($other)->allows('update', $row))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $row))->toBeFalse()
        ->and(Gate::forUser(null)->allows('view', $row))->toBeFalse();
})->with('rows only their owner may see');

it('shows public rows to everyone and lets only the owner change them', function (Closure $create): void {
    $owner = User::factory()->create();
    $row = $create($owner);
    $other = User::factory()->create();

    expect(Gate::forUser($other)->allows('view', $row))->toBeTrue()
        ->and(Gate::forUser(null)->allows('view', $row))->toBeTrue()
        ->and(Gate::forUser($other)->allows('update', $row))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $row))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('update', $row))->toBeTrue();
})->with('rows anyone may see');

it('shows a reply to the owner of the request, who cannot change it', function (): void {
    $request = ServiceRequest::factory()->create();
    $reply = RequestReply::factory()->for($request, 'request')->create();

    expect(Gate::forUser($request->user)->allows('view', $reply))->toBeTrue()
        ->and(Gate::forUser($request->user)->allows('update', $reply))->toBeFalse()
        ->and(Gate::forUser($request->user)->allows('delete', $reply))->toBeFalse();
});

it('lets nobody change a place that has no owner yet', function (): void {
    $place = Place::factory()->published()->create(['user_id' => null]);
    $resident = User::factory()->create();

    expect(Gate::forUser($resident)->allows('view', $place))->toBeTrue()
        ->and(Gate::forUser($resident)->allows('update', $place))->toBeFalse()
        ->and(Gate::forUser($resident)->allows('delete', $place))->toBeFalse();
});
