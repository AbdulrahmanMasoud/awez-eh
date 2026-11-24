<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Geography\Models\City;
use App\Domains\Places\Models\PlaceCategory;
use App\Domains\Services\Models\Trade;
use Database\Factories\Support\SadatCity;
use Illuminate\Database\Seeder;

/**
 * The city, its districts, the trades and the place categories: data every environment needs,
 * production included. Idempotent: it updates rows by slug, so it can run on every deploy.
 *
 * Icons are names from Lucide, the stroke icon set closest to the admin panel's Heroicons.
 */
final class ReferenceDataSeeder extends Seeder
{
    /**
     * The residential areas the city uses. Incomplete: the owner will send the full list, and
     * admins add districts in the panel (DECISIONS.md). Centres stay empty until then.
     *
     * @var list<array{string, string, string}>
     */
    private const array DISTRICTS = [
        ['residential-1', 'المنطقة السكنية الأولى', 'First Residential District'],
        ['residential-2', 'المنطقة السكنية الثانية', 'Second Residential District'],
        ['residential-4', 'المنطقة السكنية الرابعة', 'Fourth Residential District'],
        ['residential-7', 'المنطقة السكنية السابعة', 'Seventh Residential District'],
        ['residential-8', 'المنطقة السكنية الثامنة', 'Eighth Residential District'],
        ['residential-11', 'المنطقة السكنية الحادية عشرة', 'Eleventh Residential District'],
        ['industrial-zone', 'المنطقة الصناعية', 'Industrial Zone'],
    ];

    /** @var list<array{string, string, string, string}> */
    private const array TRADES = [
        ['plumber', 'سباك', 'Plumber', 'wrench'],
        ['electrician', 'كهربائي', 'Electrician', 'zap'],
        ['carpenter', 'نجار', 'Carpenter', 'hammer'],
        ['painter', 'نقاش', 'Painter', 'paint-roller'],
        ['plasterer', 'محارة', 'Plasterer', 'brick-wall'],
        ['tiler', 'سيراميك', 'Tiler', 'grid-2x2'],
        ['aluminium-windows', 'ألوميتال', 'Aluminium windows', 'app-window'],
        ['air-conditioning', 'تكييف وتبريد', 'Air conditioning and refrigeration', 'snowflake'],
        ['appliance-repair', 'صيانة أجهزة', 'Appliance repair', 'washing-machine'],
        ['blacksmith', 'حداد', 'Blacksmith', 'anvil'],
        ['cleaner', 'عامل نظافة', 'Cleaner', 'brush-cleaning'],
        ['furniture-movers', 'نقل عفش', 'Furniture movers', 'truck'],
        ['private-tutor', 'مدرس خصوصي', 'Private tutor', 'graduation-cap'],
    ];

    /**
     * Top-level categories, each with its subcategories.
     *
     * @var list<array{string, string, string, string, list<array{string, string, string, string}>}>
     */
    private const array PLACE_CATEGORIES = [
        ['restaurants', 'مطاعم', 'Restaurants', 'utensils', [
            ['popular-food', 'مأكولات شعبية', 'Popular food', 'soup'],
            ['grills', 'مشويات', 'Grills', 'flame'],
            ['pizza', 'بيتزا', 'Pizza', 'pizza'],
            ['cafes', 'كافيهات', 'Cafés', 'coffee'],
        ]],
        ['pharmacies', 'صيدليات', 'Pharmacies', 'pill', []],
        ['supermarkets', 'سوبر ماركت', 'Supermarkets', 'shopping-cart', []],
        ['bakeries', 'مخابز', 'Bakeries', 'croissant', []],
        ['clothing', 'ملابس', 'Clothing', 'shirt', []],
        ['mobile-phones', 'موبايلات', 'Mobile phones', 'smartphone', []],
        ['housewares', 'أدوات منزلية', 'Housewares', 'cooking-pot', []],
        ['building-supplies', 'مستلزمات بناء', 'Building supplies', 'hard-hat', []],
    ];

    public function run(): void
    {
        $city = City::query()->updateOrCreate(
            ['slug' => 'sadat-city'],
            ['name_ar' => 'مدينة السادات', 'name_en' => 'Sadat City', 'center' => SadatCity::centre(), 'is_active' => true],
        );

        foreach (self::DISTRICTS as $index => [$slug, $arabic, $english]) {
            $city->districts()->updateOrCreate(['slug' => $slug], ['name_ar' => $arabic, 'name_en' => $english, 'sort_order' => $index + 1]);
        }

        foreach (self::TRADES as $index => [$slug, $arabic, $english, $icon]) {
            Trade::query()->updateOrCreate(
                ['slug' => $slug],
                ['name_ar' => $arabic, 'name_en' => $english, 'icon' => $icon, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }

        foreach (self::PLACE_CATEGORIES as $index => [$slug, $arabic, $english, $icon, $children]) {
            $parent = $this->placeCategory($slug, $arabic, $english, $icon, $index + 1, null);

            foreach ($children as $childIndex => [$childSlug, $childArabic, $childEnglish, $childIcon]) {
                $this->placeCategory($childSlug, $childArabic, $childEnglish, $childIcon, $childIndex + 1, $parent);
            }
        }
    }

    private function placeCategory(string $slug, string $arabic, string $english, string $icon, int $order, ?PlaceCategory $parent): PlaceCategory
    {
        return PlaceCategory::query()->updateOrCreate(['slug' => $slug], [
            'parent_id' => $parent?->id,
            'name_ar' => $arabic,
            'name_en' => $english,
            'icon' => $icon,
            'sort_order' => $order,
            'is_active' => true,
        ]);
    }
}
