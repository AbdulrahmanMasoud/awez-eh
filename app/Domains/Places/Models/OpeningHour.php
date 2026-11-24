<?php

declare(strict_types=1);

namespace App\Domains\Places\Models;

use Database\Factories\OpeningHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One opening interval of a place on a weekday (0 is Sunday).
 *
 * `opens_at` and `closes_at` are Cairo wall-clock times, the one exception to "time is UTC":
 * a shop opens at 9 in the morning whatever the offset is that day (DECISIONS.md). A closing time
 * earlier than the opening time is on the next day, and the same time means open for 24 hours.
 * Several rows on one weekday are split shifts.
 */
#[Fillable(['weekday', 'opens_at', 'closes_at'])]
#[UseFactory(OpeningHourFactory::class)]
final class OpeningHour extends Model
{
    /** @use HasFactory<OpeningHourFactory> */
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
            'weekday' => 'integer',
        ];
    }
}
