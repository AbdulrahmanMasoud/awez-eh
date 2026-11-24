<?php

declare(strict_types=1);

use App\Domains\Commissions\Enums\CommissionEntryKind;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Support\CommissionBalance;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Money;

mutates(CommissionBalance::class);

function commissionEntry(CommissionEntryKind $kind, string $amount, string $currency = 'EGP'): CommissionEntry
{
    return new CommissionEntry(['kind' => $kind, 'amount' => Money::of($amount, $currency)]);
}

/**
 * @return array{string, string, string, string}
 */
function balanceOf(CommissionBalance $balance): array
{
    return [
        (string) $balance->expected->getAmount(),
        (string) $balance->invoiced->getAmount(),
        (string) $balance->collected->getAmount(),
        (string) $balance->outstanding->getAmount(),
    ];
}

it('is all zero, in pounds, for a property with no entries', function (): void {
    $balance = CommissionBalance::of([]);

    expect(balanceOf($balance))->toBe(['0.00', '0.00', '0.00', '0.00'])
        ->and($balance->outstanding->getCurrency()->getCurrencyCode())->toBe('EGP');
});

it('adds each kind of entry to its own total', function (): void {
    $balance = CommissionBalance::of([
        commissionEntry(CommissionEntryKind::Expected, '20000'),
        commissionEntry(CommissionEntryKind::Invoiced, '20000'),
        commissionEntry(CommissionEntryKind::Collected, '12500.50'),
    ]);

    expect(balanceOf($balance))->toBe(['20000.00', '20000.00', '12500.50', '7499.50']);
});

it('owes what was invoiced and not yet collected', function (): void {
    $balance = CommissionBalance::of([
        commissionEntry(CommissionEntryKind::Invoiced, '15000'),
        commissionEntry(CommissionEntryKind::Invoiced, '5000'),
        commissionEntry(CommissionEntryKind::Collected, '6000'),
        commissionEntry(CommissionEntryKind::Collected, '4000'),
    ]);

    expect(balanceOf($balance))->toBe(['0.00', '20000.00', '10000.00', '10000.00']);
});

it('takes a cancelled amount off what is expected, and nothing else', function (): void {
    $balance = CommissionBalance::of([
        commissionEntry(CommissionEntryKind::Expected, '30000'),
        commissionEntry(CommissionEntryKind::Expected, '10000'),
        commissionEntry(CommissionEntryKind::Cancelled, '30000'),
        commissionEntry(CommissionEntryKind::Invoiced, '10000'),
    ]);

    expect(balanceOf($balance))->toBe(['10000.00', '10000.00', '0.00', '10000.00']);
});

it('keeps the currency it is asked for', function (): void {
    $balance = CommissionBalance::of([commissionEntry(CommissionEntryKind::Collected, '100', 'USD')], 'USD');

    expect(balanceOf($balance))->toBe(['0.00', '0.00', '100.00', '-100.00'])
        ->and($balance->collected->getCurrency()->getCurrencyCode())->toBe('USD');
});

it('refuses to add amounts in different currencies', function (): void {
    expect(fn (): CommissionBalance => CommissionBalance::of([commissionEntry(CommissionEntryKind::Expected, '100', 'USD')]))
        ->toThrow(MoneyMismatchException::class);
});
