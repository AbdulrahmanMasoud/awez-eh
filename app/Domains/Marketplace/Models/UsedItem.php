<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Models;

use App\Domains\Conversations\Models\Conversation;
use App\Domains\Favorites\Models\Favorite;
use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Enums\ItemCondition;
use App\Domains\Marketplace\Enums\UsedItemCategory;
use App\Domains\Marketplace\Policies\UsedItemPolicy;
use App\Domains\Media\Concerns\InteractsWithMediaCollections;
use App\Domains\Media\Contracts\HasMediaCollections;
use App\Domains\Media\Settings\MediaSettings;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Models\Concerns\Moderated;
use App\Domains\Moderation\Models\Concerns\Reportable;
use App\Domains\Promotions\Models\FeaturedPlacement;
use App\Support\Casts\MoneyCast;
use App\Support\Casts\PointCast;
use App\Support\Geo\HasLocation;
use App\Support\Text\HasSearchText;
use Database\Factories\UsedItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A second-hand item a resident sells.
 */
#[Fillable(['category', 'title', 'description', 'price', 'negotiable', 'condition', 'city_id', 'district_id', 'location'])]
#[UseFactory(UsedItemFactory::class)]
#[UsePolicy(UsedItemPolicy::class)]
final class UsedItem extends Model implements HasMediaCollections
{
    /** @use HasFactory<UsedItemFactory> */
    use HasFactory;

    use HasLocation;
    use HasSearchText;
    use HasUuids;
    use InteractsWithMediaCollections;
    use Moderated;
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
     * Whether any other row points at it: then it is archived, never deleted (DeleteAccount).
     */
    public function isPointedAt(): bool
    {
        return $this->conversations()->exists()
            || $this->favorites()->exists()
            || $this->featuredPlacements()->exists()
            || $this->reports()->exists();
    }

    public function registerMediaCollections(): void
    {
        $this->addImageCollection('photos');
    }

    protected function collectionLimits(MediaSettings $settings): array
    {
        return ['photos' => $settings->used_item_photos];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => UsedItemCategory::class,
            'price' => MoneyCast::class,
            'negotiable' => 'boolean',
            'condition' => ItemCondition::class,
            'location' => PointCast::class,
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
