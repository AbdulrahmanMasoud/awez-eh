<?php

declare(strict_types=1);

use App\Support\Casts\MoneyCast;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

mutates(MoneyCast::class);

/**
 * A model that exists only here, with a price in the default currency column and a deposit
 * that keeps its own currency, as a listing will.
 */
function moneyModel(array $attributes = []): Model
{
    $model = new #[Unguarded] class extends Model
    {
        protected function casts(): array
        {
            return [
                'price' => MoneyCast::class,
                'deposit' => MoneyCast::class.':deposit_currency,USD',
            ];
        }
    };

    return $model->setRawAttributes($attributes);
}

it('stores money as minor units and its currency', function (): void {
    $model = moneyModel();

    $model->price = Money::of('1250.75', 'EGP');

    expect($model->getAttributes())->toBe(['price' => 125075, 'currency' => 'EGP']);
});

it('reads back the money it stored', function (): void {
    $model = moneyModel();
    $model->price = Money::of('1250.75', 'EGP');

    $stored = moneyModel($model->getAttributes());

    expect($stored->price)->toBeInstanceOf(Money::class)
        ->and($stored->price->isEqualTo(Money::of('1250.75', 'EGP')))->toBeTrue()
        ->and((string) $stored->price)->toBe('EGP 1250.75');
});

it('reads minor units that the database returns as a numeric string', function (): void {
    expect((string) moneyModel(['price' => '125075', 'currency' => 'EGP'])->price)->toBe('EGP 1250.75');
});

it('keeps amounts a float cannot hold exactly', function (): void {
    // 2^53 + 1 piasters: a float would round it to 2^53.
    $model = moneyModel(['price' => '9007199254740993', 'currency' => 'EGP']);

    expect($model->price->getMinorAmount()->toInt())->toBe(9_007_199_254_740_993);

    $model->price = Money::ofMinor('9007199254740993', 'EGP');

    expect($model->getAttributes()['price'])->toBe(9_007_199_254_740_993);
});

it('uses its own currency column and default currency when configured', function (): void {
    $model = moneyModel(['deposit' => 50_000]);

    expect((string) $model->deposit)->toBe('USD 500.00');

    $model->deposit = Money::of(20, 'EGP');

    expect($model->getAttributes())->toBe(['deposit' => 2000, 'deposit_currency' => 'EGP']);
});

it('reads a row in the currency it was stored in', function (): void {
    expect((string) moneyModel(['price' => 1000, 'currency' => 'USD'])->price)->toBe('USD 10.00')
        ->and((string) moneyModel(['deposit' => 1000, 'deposit_currency' => 'EGP'])->deposit)->toBe('EGP 10.00');
});

it('reads a row without a currency in the default currency', function (): void {
    expect((string) moneyModel(['price' => 1000, 'currency' => null])->price)->toBe('EGP 10.00')
        ->and((string) moneyModel(['price' => 1000])->price)->toBe('EGP 10.00');
});

it('stores and reads null', function (): void {
    $model = moneyModel(['currency' => 'EGP']);

    $model->price = null;

    expect($model->getAttributes())->toBe(['currency' => 'EGP', 'price' => null])
        ->and($model->price)->toBeNull();
});

it('accepts only Money', function (mixed $value): void {
    $model = moneyModel();

    expect(fn (): mixed => $model->price = $value)->toThrow(function (InvalidArgumentException $exception): void {
        expect($exception->getMessage())->toBe('[price] takes an instance of Brick\\Money\\Money, never a number or a string.');
    });
})->with([
    'a float' => 10.5,
    'an integer' => 1050,
    'a numeric string' => '10.50',
    'an array' => [['amount' => 1050, 'currency' => 'EGP']],
    'another object' => new stdClass,
]);

it('refuses a stored value that is not whole minor units', function (mixed $stored): void {
    expect(fn () => moneyModel(['price' => $stored, 'currency' => 'EGP'])->price)
        ->toThrow(InvalidArgumentException::class);
})->with([
    'a float' => 10.5,
    'a decimal string' => '10.5',
    'text' => 'ten',
    'two minus signs' => '--5',
    'a number inside text' => 'EGP 10',
    'a boolean' => true,
]);

it('reads negative amounts, as a refund will be', function (): void {
    expect((string) moneyModel(['price' => '-2500', 'currency' => 'EGP'])->price)->toBe('EGP -25.00');
});

it('refuses to change the currency of a row silently', function (): void {
    $model = moneyModel(['price' => 1000, 'currency' => 'EGP']);

    expect(fn (): Money => $model->price = Money::of(10, 'USD'))->toThrow(LogicException::class, 'EGP')
        ->and($model->getAttributes())->toBe(['price' => 1000, 'currency' => 'EGP']);
});

it('accepts a new amount in the currency the row already has', function (): void {
    $model = moneyModel(['price' => 1000, 'currency' => 'EGP']);

    $model->price = Money::of(25, 'EGP');

    expect($model->getAttributes())->toBe(['price' => 2500, 'currency' => 'EGP']);
});

it('refuses money with more decimals than the currency has, instead of rounding it', function (): void {
    $model = moneyModel();

    expect(fn (): Money => $model->price = Money::of('10.005', 'EGP', new CustomContext(3)))
        ->toThrow(RoundingNecessaryException::class);
});
