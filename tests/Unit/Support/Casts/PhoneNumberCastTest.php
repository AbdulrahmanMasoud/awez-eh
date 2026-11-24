<?php

declare(strict_types=1);

use App\Exceptions\InvalidPhoneNumber;
use App\Support\Casts\PhoneNumberCast;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * A model with a mobile-only `phone`, as residents' numbers are, and a `landline` that takes
 * landlines too, as a place's number does.
 */
function phoneModel(array $attributes = []): Model
{
    $model = new #[Unguarded] class extends Model
    {
        protected function casts(): array
        {
            return [
                'phone' => PhoneNumberCast::class,
                'landline' => PhoneNumberCast::class.':'.PhoneNumberCast::LANDLINES,
            ];
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

it('stores and reads a landline in a column that takes landlines', function (): void {
    $model = phoneModel();

    $model->landline = PhoneNumber::fromMobileOrLandline('048 234 5678');

    expect($model->getAttributes())->toBe(['landline' => '+20482345678'])
        ->and(phoneModel(['landline' => '+20482345678'])->landline?->national())->toBe('0482345678')
        ->and(phoneModel(['landline' => '+201012345678'])->landline?->isMobile())->toBeTrue();
});

it('refuses a landline in a mobile-only column', function (): void {
    $model = phoneModel();

    expect(fn (): mixed => $model->phone = PhoneNumber::fromMobileOrLandline('0482345678'))
        ->toThrow(InvalidArgumentException::class, '[phone] takes a mobile number');
});

it('refuses a stored landline in a mobile-only column', function (): void {
    expect(fn () => phoneModel(['phone' => '+20482345678'])->phone)->toThrow(InvalidPhoneNumber::class);
});

it('refuses an option it does not know', function (): void {
    expect(fn (): PhoneNumberCast => new PhoneNumberCast('landline'))
        ->toThrow(InvalidArgumentException::class, 'Unknown option [landline]');
});
