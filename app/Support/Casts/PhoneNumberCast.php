<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Stores a PhoneNumber in its E.164 form (+201012345678).
 *
 * The written value is mixed on purpose: anything can be assigned, and set() refuses all but PhoneNumber.
 *
 * @implements CastsAttributes<PhoneNumber, mixed>
 */
final class PhoneNumberCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PhoneNumber
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("The stored [{$key}] is not text.");
        }

        return PhoneNumber::from($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (! $value instanceof PhoneNumber) {
            throw new InvalidArgumentException("[{$key}] takes an instance of ".PhoneNumber::class.'.');
        }

        return [$key => $value->e164()];
    }
}
