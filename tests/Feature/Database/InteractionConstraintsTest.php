<?php

declare(strict_types=1);

use App\Domains\Alerts\Models\SearchAlert;
use App\Domains\Bookings\Enums\BookingKind;
use App\Domains\Bookings\Enums\BookingStatus;
use App\Domains\Bookings\Models\Booking;
use App\Domains\CityGuide\Models\DutyPharmacy;
use App\Domains\CityGuide\Models\NewsItem;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\Lead;
use App\Domains\Conversations\Enums\ConversationStatus;
use App\Domains\Conversations\Enums\MessageKind;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Favorites\Models\Favorite;
use App\Domains\Promotions\Models\FeaturedPlacement;
use App\Domains\Reviews\Models\Review;
use App\Support\Geo\Point;
use Brick\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('refuses rows the data model forbids', function (Closure $write, string $constraint): void {
    // Each write runs in a savepoint, so the failed statement does not end the test's transaction.
    expect(fn () => DB::transaction($write))->toThrow(QueryException::class, $constraint);
})->with([
    'a text message without a body' => [
        fn () => Message::factory()->create(['kind' => MessageKind::Text, 'body' => null]),
        'messages_content_check',
    ],
    'a location message without a point' => [
        fn () => Message::factory()->create(['kind' => MessageKind::Location, 'body' => null, 'location' => null]),
        'messages_content_check',
    ],
    'a second open conversation about the same subject' => [
        function (): void {
            $first = Conversation::factory()->create();
            Conversation::factory()->create(['subject_type' => $first->subject_type, 'subject_id' => $first->subject_id, 'resident_id' => $first->resident_id]);
        },
        'conversations_one_open_index',
    ],
    'a conversation about a report' => [
        fn () => Conversation::factory()->create(['subject_type' => 'report']),
        'conversations_subject_type_check',
    ],
    'a viewing of a craftsman' => [
        fn () => Booking::factory()->create(['kind' => BookingKind::PropertyViewing]),
        'bookings_bookable_check',
    ],
    'a viewing that is on the way' => [
        fn () => Booking::factory()->propertyViewing()->create(['status' => BookingStatus::OnTheWay]),
        'bookings_visit_status_check',
    ],
    'a second review of one booking' => [
        function (): void {
            $review = Review::factory()->create();
            Review::factory()->create(['booking_id' => $review->booking_id]);
        },
        'reviews_booking_id_unique',
    ],
    'a rating of six' => [
        fn () => Review::factory()->create(['rating' => 6]),
        'reviews_rating_check',
    ],
    'a rating of zero' => [
        fn () => Review::factory()->create(['rating' => 0]),
        'reviews_rating_check',
    ],
    'a reply without its date' => [
        fn () => Review::factory()->create(['reply' => 'شكرًا', 'replied_at' => null]),
        'reviews_reply_check',
    ],
    'a hidden review without its date' => [
        fn () => Review::factory()->create(['is_hidden' => true, 'hidden_at' => null]),
        'reviews_hidden_check',
    ],
    'the same favorite twice' => [
        function (): void {
            $favorite = Favorite::factory()->create();
            Favorite::factory()->create(['user_id' => $favorite->user_id, 'favoritable_id' => $favorite->favoritable_id]);
        },
        'favorites_user_id_favoritable_type_favoritable_id_unique',
    ],
    'an alert near a point without a radius' => [
        fn () => SearchAlert::factory()->create(['location' => new Point(30.3811, 30.5266), 'radius_m' => null]),
        'search_alerts_radius_check',
    ],
    'a chat lead from a booking' => [
        fn () => Lead::factory()->create(['source_type' => 'booking']),
        'leads_source_check',
    ],
    'a second lead from one conversation' => [
        function (): void {
            $lead = Lead::factory()->create();
            Lead::factory()->create(['property_id' => $lead->property_id, 'resident_id' => $lead->resident_id, 'source_id' => $lead->source_id]);
        },
        'leads_one_per_source_index',
    ],
    'a correction without a reason' => [
        fn () => Lead::factory()->voiding(Lead::factory()->create())->create(['void_reason' => null]),
        'leads_void_check',
    ],
    'voiding one lead twice' => [
        function (): void {
            $lead = Lead::factory()->create();
            Lead::factory()->voiding($lead)->create();
            Lead::factory()->voiding($lead)->create();
        },
        'leads_voids_lead_id_unique',
    ],
    'a commission of nothing' => [
        fn () => CommissionEntry::factory()->create(['amount' => Money::zero('EGP')]),
        'commission_entries_amount_check',
    ],
    'a placement that ends when it starts' => [
        fn () => FeaturedPlacement::factory()->create(['starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-01 10:00:00']),
        'featured_placements_ends_after_start_check',
    ],
    'a pharmacy twice on duty on one night' => [
        function (): void {
            $duty = DutyPharmacy::factory()->create();
            DutyPharmacy::factory()->create(['place_id' => $duty->place_id, 'duty_date' => $duty->duty_date]);
        },
        'duty_pharmacies_place_id_duty_date_unique',
    ],
    'published news without a date' => [
        fn () => NewsItem::factory()->create(['is_published' => true, 'published_at' => null]),
        'news_items_published_check',
    ],
]);

it('allows a new conversation about a subject once the earlier one is closed', function (): void {
    $first = Conversation::factory()->create(['status' => ConversationStatus::Closed]);
    Conversation::factory()->create(['subject_type' => $first->subject_type, 'subject_id' => $first->subject_id, 'resident_id' => $first->resident_id]);

    expect(Conversation::query()->where('subject_id', $first->subject_id)->count())->toBe(2);
});

it('keeps the parents of histories: a property with a lead and a booking with events', function (): void {
    $lead = Lead::factory()->create();
    $booking = Booking::factory()->create();
    $booking->timeline()->forceCreate(['to_status' => BookingStatus::Requested, 'actor_id' => $booking->resident_id]);

    expect(fn () => DB::transaction(fn () => DB::table('properties')->where('id', $lead->property_id)->delete()))
        ->toThrow(QueryException::class, 'leads_property_id_foreign')
        ->and(fn () => DB::transaction(fn () => DB::table('bookings')->where('id', $booking->id)->delete()))
        ->toThrow(QueryException::class, 'booking_events_booking_id_foreign');
});
