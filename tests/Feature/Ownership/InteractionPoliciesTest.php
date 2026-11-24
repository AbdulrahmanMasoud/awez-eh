<?php

declare(strict_types=1);

use App\Domains\Alerts\Models\SearchAlert;
use App\Domains\Bookings\Models\Booking;
use App\Domains\Commissions\Models\Lead;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Favorites\Models\Favorite;
use App\Domains\Identity\Models\User;
use App\Domains\Promotions\Models\FeaturedPlacement;
use App\Domains\Reviews\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

// Ownership proven at the policy level (invariant 1, definition of done 6). Each row comes with the
// users who may see it; the API-level proof comes with each endpoint.

dataset('private rows and who may see them', [
    'a conversation, by its resident' => function (): array {
        $conversation = Conversation::factory()->create();

        return [$conversation, $conversation->resident];
    },
    'a conversation, by its counterpart' => function (): array {
        $conversation = Conversation::factory()->create();

        return [$conversation, $conversation->counterpart];
    },
    'a message, by the other participant' => function (): array {
        $message = Message::factory()->create();

        return [$message, $message->conversation?->counterpart];
    },
    'a booking, by its resident' => function (): array {
        $booking = Booking::factory()->create();

        return [$booking, $booking->resident];
    },
    'a booking, by its counterpart' => function (): array {
        $booking = Booking::factory()->create();

        return [$booking, $booking->counterpart];
    },
    'a hidden review, by its reviewer' => function (): array {
        $review = Review::factory()->hidden()->create();

        return [$review, $review->reviewer];
    },
    'a favorite' => function (): array {
        $favorite = Favorite::factory()->create();

        return [$favorite, $favorite->user];
    },
    'a search alert' => function (): array {
        $alert = SearchAlert::factory()->create();

        return [$alert, $alert->user];
    },
    'a featured placement, by the advertiser who bought it' => function (): array {
        $placement = FeaturedPlacement::factory()->create();

        return [$placement, $placement->user];
    },
]);

it('shows a private row to the users it belongs to', function (Closure $rows): void {
    [$row, $user] = $rows();

    expect($user)->toBeInstanceOf(User::class)
        ->and(Gate::forUser($user)->allows('view', $row))->toBeTrue();
})->with('private rows and who may see them');

it('keeps another resident and guests from viewing, updating or deleting it', function (Closure $rows): void {
    [$row] = $rows();
    $other = User::factory()->create();

    expect(Gate::forUser($other)->allows('view', $row))->toBeFalse()
        ->and(Gate::forUser($other)->allows('update', $row))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $row))->toBeFalse()
        ->and(Gate::forUser(null)->allows('view', $row))->toBeFalse();
})->with('private rows and who may see them');

it('lets the owner change their favorites, alerts and placements, and the reviewer their review', function (Closure $rows): void {
    /** @var array{Model, User} $pair */
    $pair = $rows();
    [$row, $owner] = $pair;

    expect(Gate::forUser($owner)->allows('update', $row))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $row))->toBeTrue();
})->with([
    'a favorite' => fn (): array => [$favorite = Favorite::factory()->create(), $favorite->user],
    'a search alert' => fn (): array => [$alert = SearchAlert::factory()->create(), $alert->user],
    'a featured placement' => fn (): array => [$placement = FeaturedPlacement::factory()->create(), $placement->user],
    'a review' => fn (): array => [$review = Review::factory()->create(), $review->reviewer],
]);

it('lets the participants update a conversation and a booking, and nobody delete one', function (Closure $rows): void {
    [$row, $first, $second] = $rows();

    foreach ([$first, $second] as $participant) {
        expect(Gate::forUser($participant)->allows('update', $row))->toBeTrue()
            ->and(Gate::forUser($participant)->allows('delete', $row))->toBeFalse();
    }
})->with([
    'a conversation' => fn (): array => [$conversation = Conversation::factory()->create(), $conversation->resident, $conversation->counterpart],
    'a booking' => fn (): array => [$booking = Booking::factory()->create(), $booking->resident, $booking->counterpart],
]);

it('lets only its sender change or delete a message', function (): void {
    $message = Message::factory()->create();
    $conversation = $message->conversation;

    expect(Gate::forUser($message->sender)->allows('update', $message))->toBeTrue()
        ->and(Gate::forUser($message->sender)->allows('delete', $message))->toBeTrue()
        ->and(Gate::forUser($conversation?->counterpart)->allows('update', $message))->toBeFalse()
        ->and(Gate::forUser($conversation?->counterpart)->allows('delete', $message))->toBeFalse();
});

it('shows a review that is not hidden to everyone, and lets only its reviewer change it', function (): void {
    $review = Review::factory()->create();
    $other = User::factory()->create();

    expect(Gate::forUser($other)->allows('view', $review))->toBeTrue()
        ->and(Gate::forUser(null)->allows('view', $review))->toBeTrue()
        ->and(Gate::forUser($other)->allows('update', $review))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $review))->toBeFalse();
});

it('shows a lead to nobody through the API, not even its resident: leads are the platform\'s', function (): void {
    $lead = Lead::factory()->create();

    foreach ([$lead->resident, $lead->property?->user, User::factory()->create(), null] as $user) {
        expect(Gate::forUser($user)->allows('view', $lead))->toBeFalse()
            ->and(Gate::forUser($user)->allows('update', $lead))->toBeFalse()
            ->and(Gate::forUser($user)->allows('delete', $lead))->toBeFalse();
    }
});
