<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use Faker\Generator;

/**
 * Names that read like the city's residents, for demo accounts and profiles.
 */
final class ArabicNames
{
    private const array MEN = [
        'محمد', 'أحمد', 'محمود', 'مصطفى', 'إبراهيم', 'خالد', 'عمرو', 'حسن', 'علي', 'سيد',
        'رضا', 'عادل', 'ياسر', 'طارق', 'شريف', 'وليد', 'هاني', 'أشرف', 'سامح', 'جمال',
    ];

    private const array WOMEN = ['منى', 'سارة', 'هبة', 'نهى', 'دينا', 'ريم', 'إيمان', 'فاطمة', 'مريم', 'ياسمين'];

    private const array FIRST = [...self::MEN, ...self::WOMEN];

    private const array FAMILY = [
        'عبد الحميد', 'السيد', 'إبراهيم', 'حسن', 'منصور', 'عبد الله', 'فرج', 'الشافعي', 'سليمان',
        'عبد العزيز', 'الجمال', 'رمضان', 'عثمان', 'شعبان', 'عبد الرحمن', 'المصري', 'زكي', 'نصار',
    ];

    public static function person(Generator $faker): string
    {
        return Random::element($faker, self::FIRST).' '.Random::element($faker, self::FAMILY);
    }

    /** Craftsmen in the city are men today; the column has no such rule. */
    public static function craftsman(Generator $faker): string
    {
        return Random::element($faker, self::MEN).' '.Random::element($faker, self::FAMILY);
    }
}
