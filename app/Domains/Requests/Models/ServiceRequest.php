<?php

declare(strict_types=1);

namespace App\Domains\Requests\Models;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Concerns\InteractsWithMediaCollections;
use App\Domains\Media\Contracts\HasMediaCollections;
use App\Domains\Media\Settings\MediaSettings;
use App\Domains\Moderation\Models\Concerns\Reportable;
use App\Domains\Places\Models\PlaceCategory;
use App\Domains\Requests\Enums\RequestArea;
use App\Domains\Requests\Enums\RequestStatus;
use App\Domains\Requests\Policies\ServiceRequestPolicy;
use App\Domains\Services\Models\Trade;
use App\Support\Casts\MoneyCast;
use App\Support\Casts\PointCast;
use App\Support\Geo\HasLocation;
use App\Support\Text\HasSearchText;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "What do you want": a resident's request, public as soon as it is written (reports handle abuse).
 * Named ServiceRequest because Request is the HTTP class; the table is `requests`.
 */
#[Fillable(['area', 'trade_id', 'place_category_id', 'body', 'budget', 'city_id', 'district_id', 'location', 'expires_at'])]
#[Table(name: 'requests')]
#[UseFactory(ServiceRequestFactory::class)]
#[UsePolicy(ServiceRequestPolicy::class)]
final class ServiceRequest extends Model implements HasMediaCollections
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    use HasLocation;
    use HasSearchText;
    use HasUuids;
    use InteractsWithMediaCollections;
    use Reportable;

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
     * @return BelongsTo<Trade, $this>
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * @return BelongsTo<PlaceCategory, $this>
     */
    public function placeCategory(): BelongsTo
    {
        return $this->belongsTo(PlaceCategory::class);
    }

    /**
     * @return HasMany<RequestReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(RequestReply::class, 'request_id');
    }

    /**
     * Whether residents can still see and answer it: open and not past its expiry, even before
     * the scheduled job marks it expired.
     */
    public function isOpen(): bool
    {
        return $this->status === RequestStatus::Open && $this->expires_at->isFuture();
    }

    /** What the resident shows of the job: a broken tap, a wall to paint. */
    public function registerMediaCollections(): void
    {
        $this->addImageCollection('photos');
    }

    protected function collectionLimits(MediaSettings $settings): array
    {
        return ['photos' => $settings->request_photos];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area' => RequestArea::class,
            'budget' => MoneyCast::class,
            'location' => PointCast::class,
            'status' => RequestStatus::class,
            'expires_at' => 'datetime',
            'replies_count' => 'integer',
            'views_count' => 'integer',
        ];
    }

    protected function searchableAttributes(): array
    {
        return ['body'];
    }
}
