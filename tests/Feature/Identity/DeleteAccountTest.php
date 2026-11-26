<?php

declare(strict_types=1);

use App\Domains\Alerts\Models\SearchAlert;
use App\Domains\Bookings\Models\Booking;
use App\Domains\Commissions\Models\Lead;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Favorites\Models\Favorite;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\DeviceToken;
use App\Domains\Identity\Models\PhoneVerification;
use App\Domains\Identity\Models\SocialAccount;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Enums\ReportStatus;
use App\Domains\Moderation\Models\Report;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\Requests\Enums\RequestStatus;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Reviews\Models\Review;
use App\Domains\Services\Models\Provider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\IdTokens;

beforeEach(function (): void {
    Date::setTestNow('2026-11-25 18:00:00');
    $this->user = User::factory()->withPhone('01012345678')->create([
        'name' => 'أحمد السيد',
        'email' => 'ahmed.elsayed@gmail.com',
        'avatar_url' => 'https://lh3.googleusercontent.com/a/ahmed',
        'district_id' => District::factory(),
    ]);
    $this->token = $this->user->createToken('Pixel 8')->plainTextToken;
});

function deleteAccount(string $token): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withToken($token)->deleteJson('/api/v1/me');
}

it('anonymizes the account and keeps its row', function (): void {
    deleteAccount($this->token)->assertNoContent();

    $row = (array) DB::table('users')->where('id', $this->user->id)->sole();

    expect($row)->toMatchArray([
        'name' => 'حساب محذوف',
        'email' => null,
        'email_verified_at' => null,
        'password' => null,
        'remember_token' => null,
        'phone' => null,
        'phone_verified_at' => null,
        'avatar_url' => null,
        'district_id' => null,
        'status' => 'deleted',
        'suspended_at' => null,
        'deleted_at' => '2026-11-25 18:00:00',
    ]);
});

it('signs out of every device and forgets the sign-in links and confirmations', function (): void {
    $tablet = $this->user->createToken('iPad')->plainTextToken;
    DeviceToken::factory()->for($this->user)->create();
    SocialAccount::factory()->for($this->user)->create();
    SocialAccount::factory()->apple()->for($this->user)->create();
    PhoneVerification::factory()->for($this->user)->create();

    deleteAccount($this->token)->assertNoContent();

    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and(DeviceToken::query()->count())->toBe(0)
        ->and(SocialAccount::query()->count())->toBe(0)
        ->and(PhoneVerification::query()->count())->toBe(0);

    deleteAccount($this->token)->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken($tablet)->getJson('/api/v1/me')->assertUnauthorized();
});

it('archives brokered properties and keeps their agreements and leads', function (): void {
    $brokered = Property::factory()->brokered()->published()->for($this->user)->create();
    $agreement = PropertyAgreement::factory()->for($brokered)->create();
    $lead = Lead::factory()->create(['property_id' => $brokered->id]);

    deleteAccount($this->token)->assertNoContent();

    expect($brokered->fresh())->status->toBe(ContentStatus::Archived)->user_id->toBe($this->user->id)
        ->and($agreement->fresh())->not->toBeNull()
        ->and($lead->fresh())->not->toBeNull();
});

it('deletes other properties unless something points at them, which archives them', function (): void {
    $alone = Property::factory()->published()->for($this->user)->create();
    $favorited = Property::factory()->published()->for($this->user)->create();
    Favorite::factory()->create(['favoritable_id' => $favorited->id]);
    $viewed = Property::factory()->published()->for($this->user)->create();
    Booking::factory()->of($viewed)->create();
    $discussed = Property::factory()->published()->for($this->user)->create();
    Conversation::factory()->about($discussed)->create();
    $reported = Property::factory()->published()->for($this->user)->create();
    Report::factory()->create(['reportable_type' => 'property', 'reportable_id' => $reported->id]);

    deleteAccount($this->token)->assertNoContent();

    expect(Property::query()->whereKey($alone->id)->exists())->toBeFalse();

    foreach ([$favorited, $viewed, $discussed, $reported] as $property) {
        expect($property->fresh()?->status)->toBe(ContentStatus::Archived);
    }
});

it('archives the craftsman profile, which bookings and reviews point at', function (): void {
    $provider = Provider::factory()->published()->for($this->user)->create();
    $booking = Booking::factory()->of($provider)->create(['status' => 'completed']);
    $review = Review::factory()->for($booking)->create(['reviewer_id' => $booking->resident_id]);

    deleteAccount($this->token)->assertNoContent();

    expect($provider->fresh()?->status)->toBe(ContentStatus::Archived)
        ->and($review->fresh())->not->toBeNull();
});

it('releases the account\'s places, which stay as they are', function (): void {
    $place = Place::factory()->published()->for($this->user)->create();

    deleteAccount($this->token)->assertNoContent();

    expect($place->fresh())->user_id->toBeNull()->status->toBe(ContentStatus::Published);
});

it('deletes used items unless something points at them', function (): void {
    $alone = UsedItem::factory()->published()->for($this->user)->create();
    $discussed = UsedItem::factory()->published()->for($this->user)->create();
    Conversation::factory()->about($discussed)->create();

    deleteAccount($this->token)->assertNoContent();

    expect(UsedItem::query()->whereKey($alone->id)->exists())->toBeFalse()
        ->and($discussed->fresh()?->status)->toBe(ContentStatus::Archived);
});

it('deletes requests without replies and closes those with replies', function (): void {
    $open = ServiceRequest::factory()->for($this->user)->create();
    $closed = ServiceRequest::factory()->closed()->for($this->user)->create();
    $answered = ServiceRequest::factory()->for($this->user)->create();
    $reply = RequestReply::factory()->for($answered, 'request')->create();
    $repliedTo = RequestReply::factory()->for($this->user)->create();

    deleteAccount($this->token)->assertNoContent();

    expect(ServiceRequest::query()->whereKey([$open->id, $closed->id])->exists())->toBeFalse()
        ->and($answered->fresh()?->status)->toBe(RequestStatus::Closed)
        ->and($reply->fresh())->not->toBeNull()
        // Replies the account wrote stay, like its messages, under the deleted name.
        ->and($repliedTo->fresh()?->user_id)->toBe($this->user->id);
});

it('deletes favorites, search alerts and open reports, and keeps decided reports', function (): void {
    Favorite::factory()->for($this->user)->create();
    SearchAlert::factory()->for($this->user)->create();
    $open = Report::factory()->for($this->user)->create();
    $resolved = Report::factory()->for($this->user)->create(['status' => ReportStatus::Resolved, 'resolved_at' => Date::now()]);

    deleteAccount($this->token)->assertNoContent();

    expect(Favorite::query()->where('user_id', $this->user->id)->count())->toBe(0)
        ->and(SearchAlert::query()->where('user_id', $this->user->id)->count())->toBe(0)
        ->and(Report::query()->whereKey($open->id)->exists())->toBeFalse()
        ->and(Report::query()->whereKey($resolved->id)->exists())->toBeTrue();
});

it('keeps conversations, messages, bookings and reviews, under the deleted name', function (): void {
    $provider = Provider::factory()->published()->create();
    $conversation = Conversation::factory()->about($provider)->create(['resident_id' => $this->user->id, 'counterpart_id' => $provider->user_id]);
    $message = Message::factory()->for($conversation)->create(['sender_id' => $this->user->id]);
    $booking = Booking::factory()->of($provider)->create(['resident_id' => $this->user->id, 'counterpart_id' => $provider->user_id, 'status' => 'completed']);
    $review = Review::factory()->for($booking)->create(['reviewer_id' => $this->user->id]);

    deleteAccount($this->token)->assertNoContent();

    expect($conversation->fresh()?->resident?->name)->toBe('حساب محذوف')
        ->and($message->fresh()?->sender?->name)->toBe('حساب محذوف')
        ->and($booking->fresh())->not->toBeNull()
        ->and($review->fresh()?->reviewer_id)->toBe($this->user->id);
});

it('leaves the deleted account\'s personal data nowhere in any API answer', function (): void {
    deleteAccount($this->token)->assertNoContent();

    $other = User::factory()->create();
    $otherToken = $other->createToken('Pixel 8')->plainTextToken;
    app('auth')->forgetGuards();

    $answers = collect([
        $this->withToken($otherToken)->getJson('/api/v1/me'),
        $this->withToken($otherToken)->patchJson('/api/v1/me', ['name' => 'سارة']),
        $this->withToken($this->token)->getJson('/api/v1/me'),
    ])->map(fn ($response): string => (string) $response->getContent())->implode("\n");

    expect($answers)->not->toContain('أحمد السيد')
        ->not->toContain('ahmed.elsayed@gmail.com')
        ->not->toContain('1012345678')
        ->not->toContain($this->user->id);
});

it('makes a fresh account when the same Google account signs in again', function (): void {
    $tokens = new IdTokens()->publish();
    // Not the email of the account made before each test, which this sign-in would link to.
    $idToken = $tokens->google(['email' => 'mona.adel@gmail.com', 'sub' => '104857392018475620193']);
    $signIn = fn () => $this->postJson('/api/v1/auth/google', ['id_token' => $idToken, 'device_name' => 'Pixel 8']);

    $firstId = $signIn()->assertCreated()->json('data.user.id');
    deleteAccount($signIn()->assertOk()->json('data.token'))->assertNoContent();
    app('auth')->forgetGuards();

    $response = $signIn()->assertCreated();

    expect($response->json('data.user.id'))->not->toBe($firstId)
        ->and($response->json('data.user.email'))->toBe('mona.adel@gmail.com')
        ->and(User::query()->find($firstId)?->status)->toBe(UserStatus::Deleted);
});

it('does it all or nothing', function (): void {
    $brokered = Property::factory()->brokered()->published()->for($this->user)->create();

    // A domain's step fails half-way: tokens and links are already gone by then.
    Property::updating(static fn (): never => throw new RuntimeException('The disk is full.'));

    deleteAccount($this->token)->assertServerError();

    expect($this->user->fresh())->status->toBe(UserStatus::Active)->name->toBe('أحمد السيد')
        ->and($brokered->fresh()?->status)->toBe(ContentStatus::Published)
        ->and(PersonalAccessToken::query()->count())->toBe(1);
});

it('takes an admin\'s roles and authenticator away with the account', function (): void {
    $admin = User::factory()->admin(AdminRole::Moderator)->create();
    $token = $admin->createToken('Pixel 8')->plainTextToken;

    deleteAccount($token)->assertNoContent();

    expect($admin->fresh())->app_authentication_secret->toBeNull()->password->toBeNull()
        ->and($admin->fresh()?->getRoleNames()->all())->toBe([]);
});

it('refuses to delete the last super admin\'s account', function (): void {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('Pixel 8')->plainTextToken;

    deleteAccount($token)->assertStatus(409)->assertJsonPath('code', 'last_super_admin');

    expect($admin->fresh()?->status)->toBe(UserStatus::Active);
});
