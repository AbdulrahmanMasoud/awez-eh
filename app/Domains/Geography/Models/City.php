<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\CityGuide\Models\GuideEntry;
use App\Domains\CityGuide\Models\NewsItem;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Provider;
use App\Support\Casts\PointCast;
use App\Support\Text\HasTranslatedName;
use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A city the app serves. Sadat City first; the API is ready for more.
 */
#[Fillable(['name_ar', 'name_en', 'slug', 'center', 'is_active'])]
#[UseFactory(CityFactory::class)]
final class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    use HasTranslatedName;
    use HasUuids;

    /**
     * @return HasMany<GuideEntry, $this>
     */
    public function guideEntries(): HasMany
    {
        return $this->hasMany(GuideEntry::class);
    }

    /**
     * @return HasMany<NewsItem, $this>
     */
    public function newsItems(): HasMany
    {
        return $this->hasMany(NewsItem::class);
    }

    /**
     * @return HasMany<District, $this>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * @return HasMany<Provider, $this>
     */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    /**
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'center' => PointCast::class,
            'is_active' => 'boolean',
        ];
    }
}
