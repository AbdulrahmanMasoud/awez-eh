<?php

declare(strict_types=1);

namespace App\Domains\Promotions\Models;

use App\Domains\Promotions\Enums\FeaturableType;
use App\Support\Casts\MoneyCast;
use App\Support\Text\HasTranslatedName;
use Database\Factories\FeaturedPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What an advertiser can buy to feature content. Prices are still open (OPEN_QUESTIONS 2).
 */
#[Fillable(['name_ar', 'name_en', 'applies_to', 'duration_days', 'price', 'is_active', 'sort_order'])]
#[UseFactory(FeaturedPackageFactory::class)]
final class FeaturedPackage extends Model
{
    /** @use HasFactory<FeaturedPackageFactory> */
    use HasFactory;

    use HasTranslatedName;
    use HasUuids;

    /**
     * @return HasMany<FeaturedPlacement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(FeaturedPlacement::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applies_to' => FeaturableType::class,
            'duration_days' => 'integer',
            'price' => MoneyCast::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
