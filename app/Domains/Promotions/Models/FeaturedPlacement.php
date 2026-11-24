<?php

declare(strict_types=1);

namespace App\Domains\Promotions\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Promotions\Policies\FeaturedPlacementPolicy;
use App\Support\Casts\MoneyCast;
use Database\Factories\FeaturedPlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Date;

/**
 * Content featured for a while, bought with a package or granted by an admin.
 */
#[Fillable(['starts_at', 'ends_at', 'price_paid', 'cancelled_at'])]
#[UseFactory(FeaturedPlacementFactory::class)]
#[UsePolicy(FeaturedPlacementPolicy::class)]
final class FeaturedPlacement extends Model
{
    /** @use HasFactory<FeaturedPlacementFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * A property, a provider, a place or a used item.
     *
     * @return MorphTo<Model, $this>
     */
    public function featurable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * None when an admin granted it.
     *
     * @return BelongsTo<FeaturedPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(FeaturedPackage::class, 'featured_package_id');
    }

    /**
     * The advertiser who bought it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who granted it.
     *
     * @return BelongsTo<User, $this>
     */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Featured now: started, not ended (the end is exclusive), and not cancelled.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $now = Date::now();

        $query->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->whereNull('cancelled_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price_paid' => MoneyCast::class,
            'cancelled_at' => 'datetime',
        ];
    }
}
