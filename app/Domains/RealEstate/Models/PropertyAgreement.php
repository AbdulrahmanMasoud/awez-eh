<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Models;

use App\Domains\Identity\Models\User;
use App\Support\Casts\MoneyCast;
use App\Support\Casts\PhoneNumberCast;
use Database\Factories\PropertyAgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The written brokerage agreement with a property's owner (admin only). The commission is a rate in
 * basis points or a fixed amount; its value is still open (OPEN_QUESTIONS 1).
 */
#[Fillable(['owner_name', 'owner_phone', 'commission_rate_basis_points', 'commission_fixed', 'signed_on', 'ends_on', 'notes'])]
#[UseFactory(PropertyAgreementFactory::class)]
final class PropertyAgreement extends Model
{
    /** @use HasFactory<PropertyAgreementFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The admin who recorded it.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_phone' => PhoneNumberCast::class,
            'commission_rate_basis_points' => 'integer',
            'commission_fixed' => MoneyCast::class,
            'signed_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
