<?php

declare(strict_types=1);

namespace App\Domains\Places\Models;

use App\Domains\Requests\Models\ServiceRequest;
use App\Support\Text\HasTranslatedName;
use Database\Factories\PlaceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of place (restaurants, pharmacies, ...), with one level of subcategories.
 */
#[Fillable(['parent_id', 'name_ar', 'name_en', 'slug', 'icon', 'sort_order', 'is_active'])]
#[UseFactory(PlaceCategoryFactory::class)]
final class PlaceCategory extends Model
{
    /** @use HasFactory<PlaceCategoryFactory> */
    use HasFactory;

    use HasTranslatedName;
    use HasUuids;

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Place, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
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
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
