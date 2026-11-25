<?php

declare(strict_types=1);

namespace App\Domains\Commissions\Support;

use App\Domains\Commissions\Enums\CommissionEntryKind;
use App\Domains\Commissions\Models\CommissionEntry;
use Brick\Money\Money;
use LogicException;

/**
 * Where a property's commission stands, summed from its entries: a stored total would drift from
 * the history. Pure: it reads entries already loaded.
 *
 * - expected: what the platform expects to earn, net of cancelled amounts;
 * - invoiced and collected: what was billed, and what came in;
 * - outstanding: what was billed and has not come in yet.
 */
final readonly class CommissionBalance
{
    private function __construct(
        public Money $expected,
        public Money $invoiced,
        public Money $collected,
        public Money $outstanding,
    ) {}

    /**
     * @param  iterable<CommissionEntry>  $entries
     */
    public static function of(iterable $entries, string $currency = 'EGP'): self
    {
        $expected = Money::zero($currency);
        $invoiced = Money::zero($currency);
        $collected = Money::zero($currency);

        foreach ($entries as $entry) {
            // Money refuses to add another currency, so a mixed history throws.
            $amount = $entry->amount ?? throw new LogicException('A commission entry always has an amount.');

            match ($entry->kind) {
                CommissionEntryKind::Expected => $expected = $expected->plus($amount),
                CommissionEntryKind::Cancelled => $expected = $expected->minus($amount),
                CommissionEntryKind::Invoiced => $invoiced = $invoiced->plus($amount),
                CommissionEntryKind::Collected => $collected = $collected->plus($amount),
            };
        }

        return new self($expected, $invoiced, $collected, $invoiced->minus($collected));
    }
}
