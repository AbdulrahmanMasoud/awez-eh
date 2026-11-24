<?php

declare(strict_types=1);

namespace App\Domains\Services\Models;

use App\Domains\Services\Enums\PriceUnit;
use App\Support\Casts\MoneyCast;
use Database\Factories\ProviderPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a craftsman's price list: "from" a price, or a range, per visit, hour, job or square
 * metre. Both amounts share `currency`.
 */
#[Fillable(['label', 'price_from', 'price_to', 'unit', 'sort_order'])]
#[UseFactory(ProviderPriceFactory::class)]
final class ProviderPrice extends Model
{
    /** @use HasFactory<ProviderPriceFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_from' => MoneyCast::class,
            'price_to' => MoneyCast::class,
            'unit' => PriceUnit::class,
            'sort_order' => 'integer',
        ];
    }
}
