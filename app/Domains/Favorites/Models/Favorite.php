<?php

declare(strict_types=1);

namespace App\Domains\Favorites\Models;

use App\Domains\Favorites\Policies\FavoritePolicy;
use App\Domains\Identity\Models\User;
use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Something a resident saved.
 */
#[Fillable([])]
#[UseFactory(FavoriteFactory::class)]
#[UsePolicy(FavoritePolicy::class)]
final class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A property, a provider, a place or a used item.
     *
     * @return MorphTo<Model, $this>
     */
    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }
}
