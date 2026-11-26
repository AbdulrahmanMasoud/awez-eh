<?php

declare(strict_types=1);

namespace App\Domains\Places\Models;

use App\Domains\CityGuide\Models\DutyPharmacy;
use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Concerns\InteractsWithMediaCollections;
use App\Domains\Media\Contracts\HasMediaCollections;
use App\Domains\Media\Settings\MediaSettings;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Models\Concerns\Moderated;
use App\Domains\Moderation\Models\Concerns\Reportable;
use App\Domains\Places\Policies\PlacePolicy;
use App\Domains\Places\Support\OpeningHoursCalculator;
use App\Support\Casts\PhoneNumberCast;
use App\Support\Casts\PointCast;
use App\Support\Geo\HasLocation;
use App\Support\Text\HasSearchText;
use Carbon\CarbonImmutable;
use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

/**
 * A restaurant, shop or pharmacy. `phone` and `whatsapp` are the place's public numbers, not a
 * person's.
 */
#[Fillable(['place_category_id', 'name', 'description', 'phone', 'whatsapp', 'city_id', 'district_id', 'address_note', 'location', 'delivers'])]
#[UseFactory(PlaceFactory::class)]
#[UsePolicy(PlacePolicy::class)]
final class Place extends Model implements HasMediaCollections
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory;

    use HasLocation;
    use HasSearchText;
    use HasUuids;
    use InteractsWithMediaCollections;
    use Moderated;
    use Reportable;

    /**
     * Whoever claimed the place; none while an admin-added place is unclaimed.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The nights the place is the duty pharmacy.
     *
     * @return HasMany<DutyPharmacy, $this>
     */
    public function dutyNights(): HasMany
    {
        return $this->hasMany(DutyPharmacy::class);
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
     * @return BelongsTo<PlaceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PlaceCategory::class, 'place_category_id');
    }

    /**
     * @return HasMany<PlaceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaceItem::class);
    }

    /**
     * @return HasMany<PlaceOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(PlaceOffer::class);
    }

    /**
     * @return HasMany<OpeningHour, $this>
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class);
    }

    /**
     * Whether the place is open at a moment, from its opening hours (Cairo wall-clock times).
     */
    public function isOpenAt(CarbonImmutable $moment): bool
    {
        return new OpeningHoursCalculator(Config::string('awez.display_timezone'))->isOpenAt($this->openingHours, $moment);
    }

    public function registerMediaCollections(): void
    {
        $this->addImageCollection('logo')->singleFile();
        $this->addImageCollection('cover')->singleFile();
        $this->addImageCollection('photos');
    }

    protected function collectionLimits(MediaSettings $settings): array
    {
        return ['photos' => $settings->place_photos];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // A place may publish a landline; its WhatsApp is a mobile.
            'phone' => PhoneNumberCast::class.':'.PhoneNumberCast::LANDLINES,
            'whatsapp' => PhoneNumberCast::class,
            'location' => PointCast::class,
            'delivers' => 'boolean',
            'status' => ContentStatus::class,
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected function searchableAttributes(): array
    {
        return ['name', 'description'];
    }
}
