<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Models;

use App\Domains\Bookings\Models\Booking;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\Lead;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Favorites\Models\Favorite;
use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Models\Concerns\Moderated;
use App\Domains\Moderation\Models\Concerns\Reportable;
use App\Domains\Promotions\Models\FeaturedPlacement;
use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Domains\RealEstate\Policies\PropertyPolicy;
use App\Support\Casts\MoneyCast;
use App\Support\Casts\PointCast;
use App\Support\Geo\HasLocation;
use App\Support\Text\HasSearchText;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A listing for sale or rent. `brokered` ones are the platform's deals: their owner's number never
 * leaves the admin panel (invariant 2).
 */
#[Fillable(['purpose', 'type', 'title', 'description', 'price', 'rent_period', 'area_m2', 'bedrooms', 'bathrooms', 'floor', 'furnished', 'finishing', 'city_id', 'district_id', 'address_note', 'location'])]
#[UseFactory(PropertyFactory::class)]
#[UsePolicy(PropertyPolicy::class)]
final class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    use HasLocation;
    use HasSearchText;
    use HasUuids;
    use Moderated;
    use Reportable;

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<CommissionEntry, $this>
     */
    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * The written brokerage agreements behind a brokered listing (admin only).
     *
     * @return HasMany<PropertyAgreement, $this>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(PropertyAgreement::class);
    }

    /**
     * Conversations residents opened about it.
     *
     * @return MorphMany<Conversation, $this>
     */
    public function conversations(): MorphMany
    {
        return $this->morphMany(Conversation::class, 'subject');
    }

    /**
     * @return MorphMany<Favorite, $this>
     */
    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    /**
     * @return MorphMany<FeaturedPlacement, $this>
     */
    public function featuredPlacements(): MorphMany
    {
        return $this->morphMany(FeaturedPlacement::class, 'featurable');
    }

    /**
     * Viewings booked of it.
     *
     * @return MorphMany<Booking, $this>
     */
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    /**
     * Whether any other row points at it: then it is archived, never deleted (DeleteAccount).
     */
    public function isPointedAt(): bool
    {
        return $this->agreements()->exists()
            || $this->leads()->exists()
            || $this->commissionEntries()->exists()
            || $this->conversations()->exists()
            || $this->bookings()->exists()
            || $this->favorites()->exists()
            || $this->featuredPlacements()->exists()
            || $this->reports()->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PropertyPurpose::class,
            'type' => PropertyType::class,
            'price' => MoneyCast::class,
            'rent_period' => RentPeriod::class,
            'area_m2' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'floor' => 'integer',
            'furnished' => 'boolean',
            'finishing' => Finishing::class,
            'location' => PointCast::class,
            'brokered' => 'boolean',
            'views_count' => 'integer',
            'status' => ContentStatus::class,
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected function searchableAttributes(): array
    {
        return ['title', 'description'];
    }
}
