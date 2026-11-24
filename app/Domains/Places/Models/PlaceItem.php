<?php

declare(strict_types=1);

namespace App\Domains\Places\Models;

use App\Support\Casts\MoneyCast;
use Database\Factories\PlaceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dish on a menu or a product on a shelf. A price may be missing ("ask in the shop").
 */
#[Fillable(['name', 'description', 'price', 'is_available', 'sort_order'])]
#[UseFactory(PlaceItemFactory::class)]
final class PlaceItem extends Model
{
    /** @use HasFactory<PlaceItemFactory> */
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
            'price' => MoneyCast::class,
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
