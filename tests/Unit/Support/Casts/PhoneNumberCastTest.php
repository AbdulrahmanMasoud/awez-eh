<?php

declare(strict_types=1);

use App\Support\Casts\PhoneNumberCast;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

function phoneModel(array $attributes = []): Model
{
    $model = new #[Unguarded] class extends Model
    {
        protected function casts(): array
        {
            return ['phone' => PhoneNumberCast::class];
        }
    };

    return $model->setRawAttributes($attributes);
}

it('stores the E.164 form', function (): void {
    $model = phoneModel();

    $model->phone = PhoneNumber::from('٠١٠١٢٣٤٥٦٧٨');

    expect($model->getAttributes())->toBe(['phone' => '+201012345678']);
});

it('reads a phone number back', function (): void {
    $phone = phoneModel(['phone' => '+201012345678'])->phone;

    expect($phone)->toBeInstanceOf(PhoneNumber::class)
        ->and($phone->national())->toBe('01012345678');
});

it('stores and reads null', function (): void {
    $model = phoneModel();

    $model->phone = null;

    expect($model->getAttributes())->toBe(['phone' => null])
        ->and($model->phone)->toBeNull();
});

it('accepts only PhoneNumber', function (mixed $value): void {
    $model = phoneModel();

    expect(fn (): mixed => $model->phone = $value)->toThrow(InvalidArgumentException::class, PhoneNumber::class);
})->with([
    'a string' => '01012345678',
    'an integer' => 1_012_345_678,
]);

it('refuses a stored value that is not text', function (): void {
    expect(fn () => phoneModel(['phone' => 1_012_345_678])->phone)->toThrow(InvalidArgumentException::class);
});
