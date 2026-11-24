<?php

declare(strict_types=1);

namespace App\Support\Text;

/**
 * Brings the ways people write the same Arabic word to one form, for storing and searching.
 * Pure: no I/O, the same input always gives the same output.
 */
final class ArabicNormalizer
{
    /**
     * Letters people type interchangeably, and Eastern digits.
     *
     * @var array<string, string>
     */
    private const array CHARACTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
        'ؤ' => 'و',
        'ئ' => 'ي',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * Diacritics (tashkeel, Quranic marks, the dagger alef), tatweel, and invisible format
     * characters such as right-to-left marks and zero-width joiners, which copy-paste brings.
     */
    private const string REMOVED = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}\x{0640}\p{Cf}]/u';

    public function normalize(string $text): string
    {
        $text = strtr($text, self::CHARACTERS);
        $text = (string) preg_replace(self::REMOVED, '', $text);
        $text = mb_strtolower($text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
