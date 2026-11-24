<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Stores a PhoneNumber in its E.164 form (+201012345678). A column takes mobiles only, unless its
 * cast says landlines too: PhoneNumberCast::class.':'.PhoneNumberCast::LANDLINES.
 *
 * The written value is mixed on purpose: anything can be assigned, and set() refuses all but PhoneNumber.
 *
 * @implements CastsAttributes<PhoneNumber, mixed>
 */
final readonly class PhoneNumberCast implements CastsAttributes
{
    /** The option for a column that takes landlines too, such as a place's phone. */
    public const string LANDLINES = 'landlines';

    private bool $landlines;

    public function __construct(string ...$options)
    {
        foreach ($options as $option) {
            if ($option !== self::LANDLINES) {
                throw new InvalidArgumentException("Unknown option [{$option}]; PhoneNumberCast takes [".self::LANDLINES.'].');
            }
        }

        $this->landlines = $options !== [];
    }

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

        return $this->landlines ? PhoneNumber::fromMobileOrLandline($value) : PhoneNumber::from($value);
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

        if (! $this->landlines && ! $value->isMobile()) {
            throw new InvalidArgumentException("[{$key}] takes a mobile number, not a landline.");
        }

        return [$key => $value->e164()];
    }
}
