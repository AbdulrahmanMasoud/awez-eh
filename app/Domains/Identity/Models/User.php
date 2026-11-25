<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Alerts\Models\SearchAlert;
use App\Domains\Bookings\Models\Booking;
use App\Domains\Bookings\Models\BookingEvent;
use App\Domains\CityGuide\Models\NewsItem;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\Lead;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Favorites\Models\Favorite;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Enums\UserLocale;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Models\Report;
use App\Domains\Places\Models\Place;
use App\Domains\Promotions\Models\FeaturedPlacement;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Reviews\Models\Review;
use App\Domains\Services\Models\Provider;
use App\Support\Casts\PhoneNumberCast;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Every user is a resident; an advertiser or a provider is one by what they own, not by a role
 * column. Admin roles come with step 1.5. Sign-in, the phone number, the status and deletion are
 * the Identity Actions' to change, so only the profile is fillable.
 */
#[Fillable(['name', 'locale', 'district_id'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    // Primary keys are UUID v7: HasUuids generates them time-ordered, so new rows append to the index.
    use HasUuids;
    use Notifiable;

    /**
     * The columns' defaults, so a new model knows them before it is saved and read back.
     *
     * @var array<string, string>
     */
    #[\Override]
    protected $attributes = [
        'status' => 'active',
        'locale' => 'ar',
    ];

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function needsPhone(): bool
    {
        return $this->phone === null;
    }

    /**
     * Owns a listing (a property or a used item) or a place.
     */
    public function isAdvertiser(): bool
    {
        return $this->properties()->exists() || $this->usedItems()->exists() || $this->places()->exists();
    }

    /**
     * Has a craftsman profile.
     */
    public function isProvider(): bool
    {
        return $this->provider()->exists();
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return HasMany<PhoneVerification, $this>
     */
    public function phoneVerifications(): HasMany
    {
        return $this->hasMany(PhoneVerification::class);
    }

    /**
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Where the resident lives, if they said.
     *
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Conversations the user opened as a resident.
     *
     * @return HasMany<Conversation, $this>
     */
    public function residentConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'resident_id');
    }

    /**
     * Conversations residents opened with the user as advertiser or craftsman.
     *
     * @return HasMany<Conversation, $this>
     */
    public function counterpartConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'counterpart_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function residentBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'resident_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function counterpartBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'counterpart_id');
    }

    /**
     * Booking changes the user made.
     *
     * @return HasMany<BookingEvent, $this>
     */
    public function bookingEvents(): HasMany
    {
        return $this->hasMany(BookingEvent::class, 'actor_id');
    }

    /**
     * Reviews the user wrote.
     *
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * Reviews this admin hid.
     *
     * @return HasMany<Review, $this>
     */
    public function hiddenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'hidden_by');
    }

    /**
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * @return HasMany<SearchAlert, $this>
     */
    public function searchAlerts(): HasMany
    {
        return $this->hasMany(SearchAlert::class);
    }

    /**
     * The user's leads as a resident.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'resident_id');
    }

    /**
     * @return HasMany<CommissionEntry, $this>
     */
    public function recordedCommissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class, 'recorded_by');
    }

    /**
     * Placements the user bought.
     *
     * @return HasMany<FeaturedPlacement, $this>
     */
    public function featuredPlacements(): HasMany
    {
        return $this->hasMany(FeaturedPlacement::class);
    }

    /**
     * @return HasMany<FeaturedPlacement, $this>
     */
    public function grantedFeaturedPlacements(): HasMany
    {
        return $this->hasMany(FeaturedPlacement::class, 'granted_by');
    }

    /**
     * @return HasMany<NewsItem, $this>
     */
    public function createdNewsItems(): HasMany
    {
        return $this->hasMany(NewsItem::class, 'created_by');
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * The craftsman profile, for an account that has one.
     *
     * @return HasOne<Provider, $this>
     */
    public function provider(): HasOne
    {
        return $this->hasOne(Provider::class);
    }

    /**
     * Places the user claimed.
     *
     * @return HasMany<Place, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    /**
     * @return HasMany<UsedItem, $this>
     */
    public function usedItems(): HasMany
    {
        return $this->hasMany(UsedItem::class);
    }

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * @return HasMany<RequestReply, $this>
     */
    public function requestReplies(): HasMany
    {
        return $this->hasMany(RequestReply::class);
    }

    /**
     * Reports the user filed.
     *
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Content this admin reviewed, and what they resolved or recorded.
     *
     * @return HasMany<Property, $this>
     */
    public function reviewedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'reviewed_by');
    }

    /**
     * @return HasMany<Provider, $this>
     */
    public function reviewedProviders(): HasMany
    {
        return $this->hasMany(Provider::class, 'reviewed_by');
    }

    /**
     * @return HasMany<Place, $this>
     */
    public function reviewedPlaces(): HasMany
    {
        return $this->hasMany(Place::class, 'reviewed_by');
    }

    /**
     * @return HasMany<UsedItem, $this>
     */
    public function reviewedUsedItems(): HasMany
    {
        return $this->hasMany(UsedItem::class, 'reviewed_by');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function resolvedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'resolved_by');
    }

    /**
     * @return HasMany<PropertyAgreement, $this>
     */
    public function createdPropertyAgreements(): HasMany
    {
        return $this->hasMany(PropertyAgreement::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone' => PhoneNumberCast::class,
            'phone_verified_at' => 'datetime',
            'locale' => UserLocale::class,
            'status' => UserStatus::class,
            'suspended_at' => 'datetime',
            'deleted_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
