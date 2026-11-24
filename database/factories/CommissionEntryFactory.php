<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Commissions\Enums\CommissionEntryKind;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\PropertyAgreement;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionEntry>
 */
final class CommissionEntryFactory extends Factory
{
    #[\Override]
    protected $model = CommissionEntry::class;

    public function definition(): array
    {
        return [
            'property_agreement_id' => PropertyAgreement::factory(),
            'property_id' => fn (array $attributes): mixed => PropertyAgreement::query()->whereKey($attributes['property_agreement_id'])->value('property_id'),
            'lead_id' => null,
            'kind' => CommissionEntryKind::Expected,
            'amount' => Money::of(20000, 'EGP'),
            'deal_price' => null,
            'occurred_on' => CarbonImmutable::today(),
            'recorded_by' => User::factory(),
            'note' => null,
        ];
    }
}
