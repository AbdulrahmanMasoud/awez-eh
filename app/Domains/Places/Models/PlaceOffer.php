<?php

declare(strict_types=1);

namespace App\Domains\Places\Models;

use Database\Factories\PlaceOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time-limited offer of a place, from `starts_at` to `ends_at` in UTC.
 */
#[Fillable(['title', 'description', 'starts_at', 'ends_at'])]
#[UseFactory(PlaceOfferFactory::class)]
final class PlaceOffer extends Model
{
    /** @use HasFactory<PlaceOfferFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
