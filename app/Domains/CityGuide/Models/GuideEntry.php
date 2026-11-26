<?php

declare(strict_types=1);

namespace App\Domains\CityGuide\Models;

use App\Domains\CityGuide\Enums\GuideCategory;
use App\Domains\Geography\Models\City;
use App\Domains\Media\Concerns\InteractsWithMediaCollections;
use App\Domains\Media\Contracts\HasMediaCollections;
use App\Domains\Media\Settings\MediaSettings;
use App\Support\Casts\PointCast;
use App\Support\Geo\HasLocation;
use App\Support\Text\HasSearchText;
use Database\Factories\GuideEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry of the city guide (emergency numbers, hospitals, transport, ...): admin content, in
 * Arabic.
 * `phone_display` is text, not a PhoneNumber: emergency lines have three digits (122) and hotlines
 * five, which PhoneNumber rightly refuses.
 */
#[Fillable(['category', 'title', 'body', 'phone_display', 'location', 'sort_order', 'is_published'])]
#[UseFactory(GuideEntryFactory::class)]
final class GuideEntry extends Model implements HasMediaCollections
{
    /** @use HasFactory<GuideEntryFactory> */
    use HasFactory;

    use HasLocation;
    use HasSearchText;
    use HasUuids;
    use InteractsWithMediaCollections;

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addImageCollection('image')->singleFile();
    }

    protected function collectionLimits(MediaSettings $settings): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => GuideCategory::class,
            'location' => PointCast::class,
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    protected function searchableAttributes(): array
    {
        return ['title', 'body'];
    }
}
