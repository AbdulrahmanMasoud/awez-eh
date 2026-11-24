<?php

declare(strict_types=1);

namespace App\Domains\Geography\Models;

use App\Domains\Alerts\Models\SearchAlert;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Provider;
use App\Support\Casts\PointCast;
use App\Support\Text\HasTranslatedName;
use Database\Factories\DistrictFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A residential area of a city, as residents name it ("المنطقة السكنية الأولى").
 */
#[Fillable(['name_ar', 'name_en', 'slug', 'center', 'sort_order'])]
#[UseFactory(DistrictFactory::class)]
final class District extends Model
{
    /** @use HasFactory<DistrictFactory> */
    use HasFactory;

    use HasTranslatedName;
    use HasUuids;

    /**
     * @return HasMany<SearchAlert, $this>
     */
    public function searchAlerts(): HasMany
    {
        return $this->hasMany(SearchAlert::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
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
            'sort_order' => 'integer',
        ];
    }
}
