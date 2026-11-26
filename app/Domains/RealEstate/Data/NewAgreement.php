<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Data;

use App\Support\PhoneNumber;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * A brokerage agreement a sales admin records: who signed it, the commission (a rate in basis
 * points or a fixed amount, exactly one of them), and its dates.
 */
final class NewAgreement extends Data
{
    public function __construct(
        public readonly string $ownerName,
        public readonly PhoneNumber $ownerPhone,
        public readonly ?int $commissionRateBasisPoints,
        public readonly ?Money $commissionFixed,
        public readonly CarbonImmutable $signedOn,
        public readonly ?CarbonImmutable $endsOn,
        public readonly ?string $notes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'owner_name' => $this->ownerName,
            'owner_phone' => $this->ownerPhone,
            'commission_rate_basis_points' => $this->commissionRateBasisPoints,
            'commission_fixed' => $this->commissionFixed,
            'signed_on' => $this->signedOn,
            'ends_on' => $this->endsOn,
            'notes' => $this->notes,
        ];
    }
}
