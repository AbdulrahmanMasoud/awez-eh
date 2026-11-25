<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Models;

use App\Domains\Alerts\Enums\AlertFrequency;
use App\Domains\Alerts\Policies\SearchAlertPolicy;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Requests\Enums\RequestArea;
use App\Support\Casts\PointCast;
use App\Support\Text\ArabicNormalizer;
use Database\Factories\SearchAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved search that notifies its resident of new matches (step 3.4). `filters` takes its shape
 * from a Data class then.
 */
#[Fillable(['area', 'query', 'filters', 'district_id', 'location', 'radius_m', 'frequency', 'is_active'])]
#[UseFactory(SearchAlertFactory::class)]
#[UsePolicy(SearchAlertPolicy::class)]
final class SearchAlert extends Model
{
    /** @use HasFactory<SearchAlertFactory> */
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
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * The query keeps its normalized form beside it, in search_text, or none without a query. A
     * saving hook, not a mutator: an attribute named `query` cannot have one, as Eloquent's
     * static query() takes that method name.
     */
    protected static function booted(): void
    {
        self::saving(static function (self $alert): void {
            $query = $alert->getAttribute('query');
            $alert->setAttribute('search_text', is_string($query) ? new ArabicNormalizer()->normalize($query) : null);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area' => RequestArea::class,
            'filters' => 'array',
            'location' => PointCast::class,
            'radius_m' => 'integer',
            'frequency' => AlertFrequency::class,
            'is_active' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }
}
