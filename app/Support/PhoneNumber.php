<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\InvalidPhoneNumber;
use App\Support\Text\ArabicNormalizer;
use Stringable;

/**
 * An Egyptian mobile number. Its string form is masked, so a log line or an error message
 * cannot leak it (CLAUDE.md invariant 8); e164() is for the few places that need the number.
 */
final readonly class PhoneNumber implements Stringable
{
    /**
     * 010, 011, 012 or 015 then 8 digits, after an optional country code: 0…, 20…, +20… or 0020….
     */
    private const string MOBILE = '/\A(?:\+20|0020|20|0)(1[0125]\d{8})\z/';

    private const string SEPARATORS = '/[\s-]/u';

    private const int VISIBLE_DIGITS = 3;

    /**
     * @param  string  $subscriber  The 10 digits after the country code, e.g. 1012345678.
     */
    private function __construct(private string $subscriber) {}

    /**
     * @throws InvalidPhoneNumber
     */
    public static function from(string $typed): self
    {
        // The normalizer turns Arabic-Indic digits into Western ones and drops the invisible
        // direction marks a number copied from a chat carries.
        $digits = (string) preg_replace(self::SEPARATORS, '', new ArabicNormalizer()->normalize($typed));

        if (preg_match(self::MOBILE, $digits, $matches) !== 1) {
            throw new InvalidPhoneNumber;
        }

        return new self($matches[1]);
    }

    public function e164(): string
    {
        return '+20'.$this->subscriber;
    }

    public function national(): string
    {
        return '0'.$this->subscriber;
    }

    public function mask(): string
    {
        $national = $this->national();
        $hidden = strlen($national) - 2 * self::VISIBLE_DIGITS;

        return substr($national, 0, self::VISIBLE_DIGITS).str_repeat('*', $hidden).substr($national, -self::VISIBLE_DIGITS);
    }

    public function equals(self $other): bool
    {
        return $this->subscriber === $other->subscriber;
    }

    public function __toString(): string
    {
        return $this->mask();
    }

    /**
     * What var_dump() and print_r() show.
     *
     * @return array{number: string}
     */
    public function __debugInfo(): array
    {
        return ['number' => $this->mask()];
    }
}
