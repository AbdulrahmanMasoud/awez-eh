<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Enums\ItemCondition;
use App\Domains\Marketplace\Enums\UsedItemCategory;
use App\Domains\Marketplace\Models\UsedItem;
use Brick\Money\Money;
use Database\Factories\Concerns\HasModerationStates;
use Database\Factories\Support\Random;
use Database\Factories\Support\SadatCity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsedItem>
 */
final class UsedItemFactory extends Factory
{
    use HasModerationStates;

    #[\Override]
    protected $model = UsedItem::class;

    /**
     * Titles and a price range in pounds, per category, as residents write them.
     *
     * @var array<string, array{list<string>, int, int}>
     */
    private const array LISTINGS = [
        'furniture' => [['غرفة نوم كاملة خشب زان', 'أنتريه ٣ قطع حالة كويسة', 'سفرة ٦ كراسي', 'دولاب ٤ ضلف'], 2000, 40000],
        'appliances' => [['غسالة توشيبا ٧ كيلو', 'تلاجة شارب ١٤ قدم', 'بوتاجاز ٥ شعلة', 'تكييف ١.٥ حصان'], 1500, 25000],
        'electronics' => [['شاشة سامسونج ٤٣ بوصة', 'لابتوب ديل كور i5', 'بلايستيشن ٤ ومعاها دراعين', 'راوتر وي فايبر'], 500, 30000],
        'mobiles' => [['آيفون ١٣ ١٢٨ جيجا', 'سامسونج A54 بالعلبة', 'شاومي ريدمي نوت ١٢', 'أوبو رينو ٨'], 3000, 35000],
        'vehicles' => [['هيونداي فيرنا ٢٠١٥ فابريكا', 'موتوسيكل هوجن ٢٠٢٢', 'عجلة جبلي مقاس ٢٦', 'سكوتر كهربا'], 3000, 450000],
        'clothes' => [['فستان سواريه مقاس ٤٠', 'جاكيت جلد رجالي', 'بدلة رجالي كحلي', 'شنطة يد أصلية'], 200, 4000],
        'kids' => [['عربية أطفال شيكو', 'سرير أطفال خشب', 'كرسي سيارة للأطفال', 'لعب أطفال بالكرتونة'], 200, 5000],
        'books' => [['كتب تالتة ثانوي علمي', 'روايات نجيب محفوظ', 'كتب كلية تجارة', 'موسوعة أطفال'], 50, 1500],
        'other' => [['عدة نجارة كاملة', 'مكتبة خشب', 'سجادة ٢×٣', 'خيمة رحلات'], 100, 6000],
    ];

    public function definition(): array
    {
        $category = Random::element(fake(), UsedItemCategory::cases());
        [$titles, $minimum, $maximum] = self::LISTINGS[$category->value];

        return [
            'user_id' => User::factory(),
            'category' => $category,
            'title' => fake()->randomElement($titles),
            'description' => implode(' ', Random::elements(fake(), [
                'مستعمل استعمال خفيف.',
                'البيع لظروف السفر.',
                'المعاينة في المنطقة السكنية، بميعاد.',
                'السعر نهائي.',
                'فيه خدش صغير مش باين.',
                'معاه الفاتورة.',
            ], fake()->numberBetween(1, 3))),
            // Rounded to fifty pounds, as people price things.
            'price' => Money::of(intdiv(fake()->numberBetween($minimum, $maximum), 50) * 50, 'EGP'),
            'negotiable' => fake()->boolean(60),
            'condition' => fake()->randomElement(ItemCondition::cases()),
            'city_id' => City::factory(),
            'district_id' => fn (array $attributes): DistrictFactory => District::factory()->state(['city_id' => $attributes['city_id']]),
            'location' => fake()->boolean(70) ? SadatCity::randomPoint($this->faker) : null,
            'views_count' => fake()->numberBetween(0, 250),
        ];
    }
}
