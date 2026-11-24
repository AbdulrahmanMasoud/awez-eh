<?php

declare(strict_types=1);

namespace App\Support\Casts;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Money as a bigint of minor units (piasters for EGP) plus a currency column. It never goes
 * through a float (CLAUDE.md invariant 3).
 *
 * In casts(): MoneyCast::class for `currency` in EGP, or MoneyCast::class.':deposit_currency,USD'.
 *
 * The written value is mixed on purpose: anything can be assigned, and set() refuses all but Money.
 *
 * @implements CastsAttributes<Money, mixed>
 */
final readonly class MoneyCast implements CastsAttributes
{
    public function __construct(
        private string $currencyColumn = 'currency',
        private string $defaultCurrency = 'EGP',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        // PostgreSQL returns a bigint as an int, or as a string past PHP's integer range.
        if (! is_int($value) && (! is_string($value) || preg_match('/\A-?\d+\z/', $value) !== 1)) {
            throw new InvalidArgumentException("The stored [{$key}] is not a whole number of minor units.");
        }

        return Money::ofMinor($value, $this->currencyOf($attributes) ?? $this->defaultCurrency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("[{$key}] takes an instance of ".Money::class.', never a number or a string.');
        }

        $currency = $value->getCurrency()->getCurrencyCode();
        $current = $this->currencyOf($attributes);

        if ($current !== null && $current !== $currency) {
            throw new LogicException("[{$key}] is in {$current}; set [{$this->currencyColumn}] first to store {$currency}.");
        }

        return [
            // toInt() throws rather than round when the amount has more decimals than the currency.
            $key => $value->getMinorAmount()->toInt(),
            $this->currencyColumn => $currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function currencyOf(array $attributes): ?string
    {
        $currency = $attributes[$this->currencyColumn] ?? null;

        return is_string($currency) ? $currency : null;
    }
}
