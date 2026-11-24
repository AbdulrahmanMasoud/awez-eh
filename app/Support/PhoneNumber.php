<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\InvalidPhoneNumber;
use App\Support\Text\ArabicNormalizer;
use Stringable;

/**
 * An Egyptian phone number: a mobile from from(), or a mobile or a landline from
 * fromMobileOrLandline(), for a place's public number. Its string form is masked, so a log line
 * or an error message cannot leak it (CLAUDE.md invariant 8); e164() is for the few places that
 * need the number.
 */
final readonly class PhoneNumber implements Stringable
{
    /** How the number starts: the trunk zero, or the country code as 20, +20 or 0020. */
    public const string PREFIX = '(?:\+20|0020|20|0)';

    /** A mobile after the prefix: 10, 11, 12 or 15, then 8 digits. */
    public const string MOBILE_DIGITS = '1[0125]\d{8}';

    /**
     * A landline after the prefix: Cairo and Giza (2) with 8 digits, Alexandria (3) and the
     * governorates' two-digit area codes with 7, Menoufia and Sadat City being 48. The list is
     * Wikipedia's "Telephone numbers in Egypt" (DECISIONS 45). The 10th of Ramadan's old 15 is
     * left out: its ten digits read as a WE mobile number missing a digit.
     */
    public const string LANDLINE_DIGITS = '(?:2[1-9]\d{7}|(?:3|13|40|45|46|47|48|50|55|57|62|64|65|66|68|69|82|84|86|88|92|93|95|96|97)[1-9]\d{6})';

    private const string MOBILE = '/\A'.self::PREFIX.'('.self::MOBILE_DIGITS.')\z/';

    private const string MOBILE_OR_LANDLINE = '/\A'.self::PREFIX.'('.self::MOBILE_DIGITS.'|'.self::LANDLINE_DIGITS.')\z/';

    private const string SEPARATORS = '/[\s-]/u';

    private const int VISIBLE_DIGITS = 3;

    /**
     * @param  string  $subscriber  The digits after the country code, e.g. 1012345678 or 482345678.
     */
    private function __construct(private string $subscriber) {}

    /**
     * A mobile number: residents' numbers, and anything a WhatsApp message is sent to.
     *
     * @throws InvalidPhoneNumber
     */
    public static function from(string $typed): self
    {
        return self::parse($typed, self::MOBILE);
    }

    /**
     * A mobile or a landline, for numbers a place publishes.
     *
     * @throws InvalidPhoneNumber
     */
    public static function fromMobileOrLandline(string $typed): self
    {
        return self::parse($typed, self::MOBILE_OR_LANDLINE);
    }

    public function isMobile(): bool
    {
        return preg_match('/\A'.self::MOBILE_DIGITS.'\z/', $this->subscriber) === 1;
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

    /**
     * @throws InvalidPhoneNumber
     */
    private static function parse(string $typed, string $pattern): self
    {
        // The normalizer turns Arabic-Indic digits into Western ones and drops the invisible
        // direction marks a number copied from a chat carries.
        $digits = (string) preg_replace(self::SEPARATORS, '', new ArabicNormalizer()->normalize($typed));

        if (preg_match($pattern, $digits, $matches) !== 1) {
            throw new InvalidPhoneNumber;
        }

        return new self($matches[1]);
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
