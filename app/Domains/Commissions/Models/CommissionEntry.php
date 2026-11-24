<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Models;

use App\Domains\Commissions\Enums\CommissionEntryKind;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Support\Casts\MoneyCast;
use App\Support\Database\IsAppendOnly;
use Database\Factories\CommissionEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a property's commission history; the totals are CommissionBalance's sums, never a
 * stored number. Append-only. `amount` and `deal_price` share `currency`.
 */
#[Fillable(['kind', 'amount', 'deal_price', 'occurred_on', 'note'])]
#[UseFactory(CommissionEntryFactory::class)]
final class CommissionEntry extends Model
{
    /** @use HasFactory<CommissionEntryFactory> */
    use HasFactory;

    use HasUuids;
    use IsAppendOnly;

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<PropertyAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PropertyAgreement::class, 'property_agreement_id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The admin who recorded it.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CommissionEntryKind::class,
            'amount' => MoneyCast::class,
            'deal_price' => MoneyCast::class,
            'occurred_on' => 'date',
            'created_at' => 'datetime',
        ];
    }
}
