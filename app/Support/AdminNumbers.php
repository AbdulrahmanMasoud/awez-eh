<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The admin panel writes Western digits (0-9) in every language, numbers, money and dates
 * included, while its text stays Arabic (owner's decision, DECISIONS 97). For Arabic that is
 * ICU's Arabic with the Latin numbering system: `1,234,567.5` and `42,500.00 ج.م.`. Carbon
 * already writes Western digits in Arabic dates.
 */
final class AdminNumbers
{
    public const string ARABIC_WITH_WESTERN_DIGITS = 'ar-u-nu-latn';

    public static function localeFor(string $appLocale): string
    {
        return $appLocale === 'ar' ? self::ARABIC_WITH_WESTERN_DIGITS : $appLocale;
    }
}
