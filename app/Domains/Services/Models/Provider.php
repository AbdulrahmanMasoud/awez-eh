<?php

declare(strict_types=1);

namespace App\Domains\Services\Models;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Models\Concerns\Moderated;
use App\Domains\Moderation\Models\Concerns\Reportable;
use App\Domains\Services\Enums\ProviderVerification;
use App\Domains\Services\Policies\ProviderPolicy;
use App\Support\Casts\PointCast;
use App\Support\Geo\HasLocation;
use App\Support\Text\HasSearchText;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A craftsman's profile, one per account. The rating is kept as a sum and a count, so the average
 * is computed, never stored as a float.
 */
#[Fillable(['display_name', 'bio', 'city_id', 'district_id', 'location', 'service_radius_m', 'years_experience', 'is_available'])]
#[UseFactory(ProviderFactory::class)]
#[UsePolicy(ProviderPolicy::class)]
final class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
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
     * @return BelongsToMany<Trade, $this>
     */
    public function trades(): BelongsToMany
    {
        return $this->belongsToMany(Trade::class, 'provider_trades')->withPivot('is_primary');
    }

    /**
     * @return HasMany<ProviderPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProviderPrice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => PointCast::class,
            'service_radius_m' => 'integer',
            'years_experience' => 'integer',
            'verification' => ProviderVerification::class,
            'verified_at' => 'datetime',
            'is_available' => 'boolean',
            'rating_sum' => 'integer',
            'rating_count' => 'integer',
            'jobs_done' => 'integer',
            'status' => ContentStatus::class,
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected function searchableAttributes(): array
    {
        return ['display_name', 'bio'];
    }
}
