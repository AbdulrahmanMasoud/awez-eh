<?php

declare(strict_types=1);

namespace App\Domains\CityGuide\Models;

use App\Domains\Places\Models\Place;
use Database\Factories\DutyPharmacyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The pharmacy on duty for a night. `duty_date` is a Cairo calendar date, the same exception to
 * "time is UTC" as opening hours (DECISIONS 40); `starts_at` and `ends_at` are UTC moments.
 */
#[Fillable(['duty_date', 'starts_at', 'ends_at'])]
#[UseFactory(DutyPharmacyFactory::class)]
final class DutyPharmacy extends Model
{
    /** @use HasFactory<DutyPharmacyFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * A pharmacy.
     *
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
            'duty_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
