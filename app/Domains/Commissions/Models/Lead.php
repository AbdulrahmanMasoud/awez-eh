<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Models;

use App\Domains\Commissions\Enums\LeadChannel;
use App\Domains\Commissions\Policies\LeadPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use App\Support\Database\IsAppendOnly;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A resident reaching a brokered listing, by chat or by a viewing: what the platform's commission
 * rests on (invariant 2). Append-only: a correction is a new row that voids an earlier one.
 */
#[Fillable(['channel', 'void_reason'])]
#[UseFactory(LeadFactory::class)]
#[UsePolicy(LeadPolicy::class)]
final class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
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
     * @return BelongsTo<User, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    /**
     * The conversation or the booking that created it.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The earlier lead this row corrects, for a correction.
     *
     * @return BelongsTo<self, $this>
     */
    public function voidedLead(): BelongsTo
    {
        return $this->belongsTo(self::class, 'voids_lead_id');
    }

    /**
     * The correction that voids this lead, if any.
     *
     * @return HasOne<self, $this>
     */
    public function voidingLead(): HasOne
    {
        return $this->hasOne(self::class, 'voids_lead_id');
    }

    /**
     * @return HasMany<CommissionEntry, $this>
     */
    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => LeadChannel::class,
            'created_at' => 'datetime',
        ];
    }
}
