<?php

declare(strict_types=1);

namespace App\Domains\Services\Models;

use App\Domains\Requests\Models\ServiceRequest;
use App\Support\Text\HasTranslatedName;
use Database\Factories\TradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A craft residents look for (plumber, electrician, ...). `icon` names an icon of the app's set.
 */
#[Fillable(['name_ar', 'name_en', 'slug', 'icon', 'sort_order', 'is_active'])]
#[UseFactory(TradeFactory::class)]
final class Trade extends Model
{
    /** @use HasFactory<TradeFactory> */
    use HasFactory;

    use HasTranslatedName;
    use HasUuids;

    /**
     * @return BelongsToMany<Provider, $this>
     */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_trades')->withPivot('is_primary');
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
