<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Models;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Enums\ItemCondition;
use App\Domains\Marketplace\Enums\UsedItemCategory;
use App\Domains\Marketplace\Policies\UsedItemPolicy;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Models\Concerns\Moderated;
use App\Domains\Moderation\Models\Concerns\Reportable;
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

/**
 * A second-hand item a resident sells.
 */
#[Fillable(['category', 'title', 'description', 'price', 'negotiable', 'condition', 'city_id', 'district_id', 'location'])]
#[UseFactory(UsedItemFactory::class)]
#[UsePolicy(UsedItemPolicy::class)]
final class UsedItem extends Model
{
    /** @use HasFactory<UsedItemFactory> */
    use HasFactory;

    use HasLocation;
    use HasSearchText;
    use HasUuids;
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
