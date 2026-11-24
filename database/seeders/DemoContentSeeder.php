<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Enums\ReportStatus;
use App\Domains\Moderation\Models\Report;
use App\Domains\Places\Models\Place;
use App\Domains\Places\Models\PlaceCategory;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\Requests\Enums\RequestArea;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Enums\PriceUnit;
use App\Domains\Services\Models\Provider;
use App\Domains\Services\Models\Trade;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\Support\ArabicNames;
use Database\Factories\Support\Random;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use LogicException;

/**
 * Made-up residents, craftsmen, listings, places and requests for a city that looks lived in.
 * Local and testing only: it refuses to run anywhere else. Needs ReferenceDataSeeder first.
 *
 * A craftsman's profile is one per account, so the 40 craftsmen have accounts of their own
 * beside the 30 residents who own the rest of the content.
 */
final class DemoContentSeeder extends Seeder
{
    /** @var list<string> */
    public const array ENVIRONMENTS = ['local', 'testing'];

    private const int RESIDENTS = 30;

    private const int PROPERTIES = 30;

    private const int BROKERED_PROPERTIES = 10;

    private const int PROVIDERS = 40;

    private const int PLACES = 40;

    private const int USED_ITEMS = 60;

    /**
     * Per trade: how a craftsman signs, what they do, and their price list (label, from, to, unit).
     *
     * @var array<string, array{string, string, list<array{string, int, int|null, PriceUnit}>}>
     */
    private const array CRAFTS = [
        'plumber' => ['الأسطى %s', 'تركيب وصيانة الأدوات الصحية وكشف التسريبات.', [['المعاينة', 100, null, PriceUnit::Visit], ['تغيير خلاط', 150, 250, PriceUnit::Job]]],
        'electrician' => ['الأسطى %s', 'تأسيس كهرباء الشقق، تركيب النجف واللوحات، وحل الأعطال.', [['المعاينة', 100, null, PriceUnit::Visit], ['تركيب نجفة', 150, 300, PriceUnit::Job]]],
        'carpenter' => ['الأسطى %s', 'تفصيل وتركيب الأبواب والمطابخ والدواليب.', [['تركيب باب', 300, 500, PriceUnit::Job], ['ساعة العمل', 150, null, PriceUnit::Hour]]],
        'painter' => ['الأسطى %s', 'دهانات حديثة وورق حائط، بلاستيك وزيت.', [['دهان المتر', 60, 120, PriceUnit::SquareMeter]]],
        'plasterer' => ['الأسطى %s', 'محارة وبياض للشقق والفيلات.', [['محارة المتر', 80, 130, PriceUnit::SquareMeter]]],
        'tiler' => ['الأسطى %s', 'تركيب سيراميك وبورسلين وأرضيات.', [['تركيب المتر', 90, 150, PriceUnit::SquareMeter]]],
        'aluminium-windows' => ['%s للألوميتال', 'شبابيك ومطابخ ألوميتال، قطاعات مصرية ومستوردة.', [['شباك ألوميتال (المتر)', 1800, 3500, PriceUnit::SquareMeter]]],
        'air-conditioning' => ['م. %s', 'تركيب وصيانة وشحن فريون لكل الأنواع.', [['تنظيف وصيانة تكييف', 250, 400, PriceUnit::Job], ['شحن فريون', 600, 1200, PriceUnit::Job]]],
        'appliance-repair' => ['%s لصيانة الأجهزة', 'صيانة الغسالات والتلاجات والبوتاجازات في البيت.', [['المعاينة', 150, null, PriceUnit::Visit]]],
        'blacksmith' => ['الأسطى %s', 'أبواب وشبابيك حديد ودرابزين.', [['باب حديد (المتر)', 2500, 4500, PriceUnit::SquareMeter]]],
        'cleaner' => ['%s لخدمات النظافة', 'تنظيف الشقق بعد التشطيب، والسجاد والكنب.', [['تنظيف شقة', 800, 1500, PriceUnit::Job], ['الساعة', 120, null, PriceUnit::Hour]]],
        'furniture-movers' => ['%s لنقل العفش', 'نقل عفش داخل المدينة وللمحافظات، مع الفك والتركيب.', [['نقل داخل المدينة', 1500, 3000, PriceUnit::Job]]],
        'private-tutor' => ['أ. %s', 'دروس خصوصية لإعدادي وثانوي: رياضيات وعلوم.', [['الحصة', 150, 300, PriceUnit::Hour]]],
    ];

    /**
     * Per place category: names, items (name and price in pounds, or none), and a week of hours.
     *
     * @var array<string, array{list<string>, list<array{string, int|null}>, string}>
     */
    private const array PLACES_BY_CATEGORY = [
        'popular-food' => [['مطعم الشبراوي', 'كشري التحرير', 'فول وطعمية أبو مازن', 'مطعم الأصيل'], [['طبق كشري صغير', 25], ['طبق كشري كبير', 40], ['ساندوتش فول', 10], ['ساندوتش طعمية', 10]], 'late'],
        'grills' => [['مشويات الحاتي', 'كبابجي السادات', 'مشويات أبو حسين'], [['ربع كيلو كفتة', 120], ['نص فرخة مشوية', 140], ['كيلو مشكل مشويات', 650]], 'late'],
        'pizza' => [['بيتزا نابولي', 'فطاطري السلطان', 'بيتزا الإيطالي'], [['بيتزا مارجريتا وسط', 120], ['فطيرة جبنة وسجق', 90], ['فطير مشلتت', 110]], 'late'],
        'cafes' => [['كافيه لمة', 'قهوة الحرية', 'كافيه سما'], [['قهوة تركي', 35], ['شاي بالنعناع', 20], ['عصير مانجو', 50], ['كابتشينو', 55]], 'cafe'],
        'pharmacies' => [['صيدلية الشفاء', 'صيدلية الحياة', 'صيدلية د. سارة', 'صيدلية النور'], [['قياس الضغط', 20], ['قياس السكر', 30], ['حقن في البيت', 50], ['مستلزمات أطفال', null]], 'pharmacy'],
        'supermarkets' => [['سوبر ماركت الحرمين', 'هايبر السادات', 'ماركت البركة', 'ماركت الأمانة'], [['كيلو أرز مصري', 38], ['زجاجة زيت ١ لتر', 85], ['كيلو سكر', 35], ['كرتونة بيض', 160]], 'market'],
        'bakeries' => [['مخبز السلام', 'حلواني الأمل', 'مخابز الفردوس'], [['عيش فينو (١٠ أرغفة)', 30], ['كرواسون', 15], ['كيلو بسكويت', 120], ['تورتة عيد ميلاد', 450]], 'bakery'],
        'clothing' => [['الأناقة للملابس', 'بيت الأطفال', 'ملابس الشباب'], [['تيشيرت قطن', 250], ['بنطلون جينز', 550], ['طقم أطفال', 400]], 'shop'],
        'mobile-phones' => [['السادات موبايل', 'المصري للموبايلات', 'تكنو فون'], [['جراب موبايل', 80], ['شاحن أصلي', 350], ['تغيير شاشة', null]], 'shop'],
        'housewares' => [['الأمين للأدوات المنزلية', 'بيت الهدايا', 'المنزل الحديث'], [['طقم حلل ستانلس', 1800], ['خلاط كهربائي', 1500], ['طقم كوبايات', 220]], 'shop'],
        'building-supplies' => [['الشافعي لمواد البناء', 'النجار للأدوات الصحية', 'مخازن الهدى للسيراميك'], [['شيكارة أسمنت', 230], ['متر سيراميك', 280], ['خلاط حمام', 900]], 'workshop'],
    ];

    /**
     * Opening hours per pattern, as Cairo wall-clock intervals (opens, closes) for each weekday,
     * Sunday first. A closing time before the opening time is after midnight.
     *
     * @var array<string, list<list<array{string, string}>>>
     */
    private const array WEEKS = [
        'late' => [[['12:00', '02:00']], [['12:00', '02:00']], [['12:00', '02:00']], [['12:00', '02:00']], [['12:00', '03:00']], [['13:00', '03:00']], [['12:00', '02:00']]],
        'cafe' => [[['08:00', '02:00']], [['08:00', '02:00']], [['08:00', '02:00']], [['08:00', '02:00']], [['08:00', '03:00']], [['10:00', '03:00']], [['08:00', '02:00']]],
        'pharmacy' => [[['09:00', '03:00']], [['09:00', '03:00']], [['09:00', '03:00']], [['09:00', '03:00']], [['09:00', '03:00']], [['14:00', '03:00']], [['09:00', '03:00']]],
        'market' => [[['08:00', '00:00']], [['08:00', '00:00']], [['08:00', '00:00']], [['08:00', '00:00']], [['08:00', '01:00']], [['13:00', '01:00']], [['08:00', '00:00']]],
        'bakery' => [[['06:00', '22:00']], [['06:00', '22:00']], [['06:00', '22:00']], [['06:00', '22:00']], [['06:00', '22:00']], [['06:00', '12:00']], [['06:00', '22:00']]],
        // Split shifts, and Friday evening only.
        'shop' => [[['10:00', '15:00'], ['17:00', '23:00']], [['10:00', '15:00'], ['17:00', '23:00']], [['10:00', '15:00'], ['17:00', '23:00']], [['10:00', '15:00'], ['17:00', '23:00']], [['10:00', '15:00'], ['17:00', '23:00']], [['17:00', '23:00']], [['10:00', '15:00'], ['17:00', '23:00']]],
        // Closed on Friday.
        'workshop' => [[['08:00', '17:00']], [['08:00', '17:00']], [['08:00', '17:00']], [['08:00', '17:00']], [['08:00', '17:00']], [], [['08:00', '17:00']]],
    ];

    /** @var array<string, list<string>> */
    private const array OFFERS = [
        'restaurants' => ['خصم ٢٠٪ على الطلبات فوق ٣٠٠ جنيه', 'التوصيل مجانًا للسكنية الأولى والتانية'],
        'pharmacies' => ['خصم ١٠٪ على مستحضرات التجميل', 'قياس ضغط وسكر مجانًا يوم الجمعة'],
        'supermarkets' => ['عروض آخر الشهر على الزيت والسكر', 'اشتري ٢ والتالت هدية على المنظفات'],
        'bakeries' => ['تورتة عيد الميلاد بخصم ١٥٪'],
        'clothing' => ['تخفيضات الموسم لحد ٥٠٪'],
        'mobile-phones' => ['جراب هدية مع كل شاحن'],
        'housewares' => ['خصم ١٠٪ على الأجهزة الكهربائية'],
        'building-supplies' => ['توصيل مجاني للطلبيات الكبيرة جوه المدينة'],
    ];

    private Generator $faker;

    private City $city;

    /** @var Collection<int, District> */
    private Collection $districts;

    /** @var Collection<int, User> */
    private Collection $residents;

    public function run(): void
    {
        if (! App::environment(self::ENVIRONMENTS)) {
            throw new LogicException('Demo content is made up: it is seeded in local and testing only.');
        }

        $this->faker = fake();
        $this->city = City::query()->where('slug', 'sadat-city')->sole();
        $this->districts = $this->city->districts()->get();
        // Factories bind state closures to themselves, so the generator is captured, not $this.
        $faker = $this->faker;
        $this->residents = User::factory()->count(self::RESIDENTS)
            ->state(fn (): array => ['name' => ArabicNames::person($faker)])
            ->create();

        $properties = $this->seedProperties();
        $providers = $this->seedProviders();
        $places = $this->seedPlaces();
        $usedItems = $this->seedUsedItems();
        [$requests, $replies] = $this->seedRequests($providers);

        $this->seedReports([$properties, $providers, $places, $usedItems, $requests, $replies]);
    }

    /**
     * @return Collection<int, Property>
     */
    private function seedProperties(): Collection
    {
        $properties = [];
        $statuses = $this->statuses(self::PROPERTIES, [
            ContentStatus::PendingReview, ContentStatus::PendingReview, ContentStatus::PendingReview,
            ContentStatus::Rejected, ContentStatus::Paused, ContentStatus::Sold, ContentStatus::Rented,
        ]);

        foreach ($statuses as $index => $status) {
            $factory = Property::factory()->recycle($this->city)->recycle($this->districts)
                ->for($this->residents->random())
                ->inStatus($status);

            $factory = match ($status) {
                ContentStatus::Sold => $factory->state(['purpose' => PropertyPurpose::Sale]),
                ContentStatus::Rented => $factory->forRent(),
                default => $factory,
            };

            // A third are the platform's own deals.
            $properties[] = ($index < self::BROKERED_PROPERTIES ? $factory->brokered() : $factory)->create();
        }

        // The agreement goes with a published brokered listing, as an admin would record it.
        $brokered = array_find($properties, fn (Property $property): bool => $property->brokered && $property->status === ContentStatus::Published)
            ?? array_find($properties, fn (Property $property): bool => $property->brokered)
            ?? throw new LogicException('The demo has brokered listings.');

        PropertyAgreement::factory()->for($brokered)->create();

        return new Collection($properties);
    }

    /**
     * @return Collection<int, Provider>
     */
    private function seedProviders(): Collection
    {
        $trades = Trade::query()->orderBy('sort_order')->get()->all();
        $providers = [];
        $statuses = $this->statuses(self::PROVIDERS, [
            ContentStatus::PendingReview, ContentStatus::PendingReview, ContentStatus::PendingReview, ContentStatus::PendingReview,
            ContentStatus::Rejected, ContentStatus::Rejected, ContentStatus::Paused, ContentStatus::Paused,
        ]);

        foreach ($statuses as $index => $status) {
            // Every trade has craftsmen: they are dealt out in turn.
            $trade = $trades[$index % count($trades)];
            [$signature, $work, $prices] = self::CRAFTS[$trade->slug];
            $name = $trade->slug === 'private-tutor' ? ArabicNames::person($this->faker) : ArabicNames::craftsman($this->faker);
            $years = $this->faker->numberBetween(2, 30);

            $provider = Provider::factory()->recycle($this->city)->recycle($this->districts)
                ->for(User::factory()->state(['name' => $name]))
                ->inStatus($status)
                ->when($status === ContentStatus::Published && $this->faker->boolean(60), fn ($factory) => $factory->verified())
                ->create(['display_name' => sprintf($signature, $name), 'bio' => "{$work} خبرة {$years} سنة.", 'years_experience' => $years]);

            $provider->trades()->attach($trade, ['is_primary' => true]);

            $others = array_values(array_filter($trades, fn (Trade $other): bool => $other->isNot($trade)));

            if ($others !== [] && $this->faker->boolean(30)) {
                $provider->trades()->attach(Random::element($this->faker, $others), ['is_primary' => false]);
            }

            foreach ($prices as $order => [$label, $from, $to, $unit]) {
                $provider->prices()->create([
                    'label' => $label,
                    'price_from' => Money::of($from, 'EGP'),
                    'price_to' => $to === null ? null : Money::of($to, 'EGP'),
                    'unit' => $unit,
                    'sort_order' => $order + 1,
                ]);
            }

            $providers[] = $provider;
        }

        return new Collection($providers);
    }

    /**
     * @return Collection<int, Place>
     */
    private function seedPlaces(): Collection
    {
        $categories = PlaceCategory::query()->whereIn('slug', array_keys(self::PLACES_BY_CATEGORY))->with('parent')->get()->keyBy('slug')->all();
        $places = [];
        $statuses = $this->statuses(self::PLACES, [
            ContentStatus::PendingReview, ContentStatus::PendingReview, ContentStatus::PendingReview, ContentStatus::PendingReview,
            ContentStatus::Rejected, ContentStatus::Paused, ContentStatus::Archived,
        ]);
        $slugs = array_keys(self::PLACES_BY_CATEGORY);

        foreach ($statuses as $index => $status) {
            $slug = $slugs[$index % count($slugs)];
            $category = $categories[$slug];
            [$names, $items, $week] = self::PLACES_BY_CATEGORY[$slug];
            $topLevel = $category->parent instanceof PlaceCategory ? $category->parent->slug : $category->slug;

            $place = Place::factory()->recycle($this->city)->recycle($this->districts)
                ->for($category, 'category')
                ->inStatus($status)
                // Half are claimed by their owners; an admin added the rest.
                ->state(['user_id' => $index % 2 === 0 ? $this->residents->random()->id : null])
                ->create([
                    'name' => $names[intdiv($index, count($slugs)) % count($names)],
                    'delivers' => in_array($topLevel, ['restaurants', 'pharmacies', 'supermarkets'], true),
                ]);

            foreach ($items as $order => [$item, $price]) {
                $place->items()->create([
                    'name' => $item,
                    'price' => $price === null ? null : Money::of($price, 'EGP'),
                    'is_available' => $this->faker->boolean(90),
                    'sort_order' => $order + 1,
                ]);
            }

            // One pharmacy is open around the clock.
            $hours = $slug === 'pharmacies' && $index < count($slugs) ? array_fill(0, 7, [['00:00', '00:00']]) : self::WEEKS[$week];

            foreach ($hours as $weekday => $intervals) {
                foreach ($intervals as [$opens, $closes]) {
                    $place->openingHours()->create(['weekday' => $weekday, 'opens_at' => $opens, 'closes_at' => $closes]);
                }
            }

            foreach (Random::elements($this->faker, self::OFFERS[$topLevel], $this->faker->numberBetween(1, count(self::OFFERS[$topLevel]))) as $title) {
                $startsAt = CarbonImmutable::now()->subDays($this->faker->numberBetween(0, 7))->startOfDay();
                $place->offers()->create(['title' => $title, 'starts_at' => $startsAt, 'ends_at' => $startsAt->addDays($this->faker->numberBetween(5, 30))]);
            }

            $places[] = $place;
        }

        return new Collection($places);
    }

    /**
     * @return Collection<int, UsedItem>
     */
    private function seedUsedItems(): Collection
    {
        $items = [];
        $statuses = $this->statuses(self::USED_ITEMS, [
            ...array_fill(0, 5, ContentStatus::PendingReview),
            ContentStatus::Rejected, ContentStatus::Rejected, ContentStatus::Paused,
            ...array_fill(0, 4, ContentStatus::Sold),
        ]);

        foreach ($statuses as $status) {
            $items[] = UsedItem::factory()->recycle($this->city)->recycle($this->districts)
                ->for($this->residents->random())
                ->inStatus($status)
                ->create();
        }

        return new Collection($items);
    }

    /**
     * @param  Collection<int, Provider>  $providers
     * @return array{Collection<int, ServiceRequest>, Collection<int, RequestReply>}
     */
    private function seedRequests(Collection $providers): array
    {
        $trades = Trade::query()->get();
        $placeCategories = PlaceCategory::query()->whereIn('slug', array_keys(self::PLACES_BY_CATEGORY))->get();
        $craftsmen = User::query()->whereIn('id', $providers->pluck('user_id'))->get();
        $requests = [];
        $replies = [];

        $areas = [
            ...array_fill(0, 7, RequestArea::Services), ...array_fill(0, 4, RequestArea::RealEstate),
            ...array_fill(0, 4, RequestArea::Places), ...array_fill(0, 3, RequestArea::Marketplace),
            ...array_fill(0, 2, RequestArea::Other),
        ];

        foreach ($areas as $index => $area) {
            $owner = $this->residents->random();
            $factory = ServiceRequest::factory()->recycle($this->city)->for($owner);
            $factory = match (true) {
                $index % 10 === 3 => $factory->closed(),
                $index % 10 === 7 => $factory->expired(),
                default => $factory,
            };

            $request = $factory->create([
                'area' => $area,
                'trade_id' => $area === RequestArea::Services ? $trades->random()->id : null,
                'place_category_id' => $area === RequestArea::Places ? $placeCategories->random()->id : null,
                'district_id' => $this->faker->boolean(70) ? $this->districts->random()->id : null,
            ]);

            // Craftsmen answer service requests; other residents answer the rest.
            $repliers = ($area === RequestArea::Services ? $craftsmen : $this->residents->where('id', '!=', $owner->id))
                ->random($this->faker->numberBetween(1, 4));

            foreach ($repliers as $replier) {
                $replies[] = RequestReply::factory()->for($request, 'request')->for($replier)->create();
            }

            $request->forceFill(['replies_count' => $repliers->count()])->save();
            $requests[] = $request;
        }

        return [new Collection($requests), new Collection($replies)];
    }

    /**
     * A few reports from residents, one on each kind of content, most of them still open.
     *
     * @param  list<Collection<int, covariant Model>>  $contents
     */
    private function seedReports(array $contents): void
    {
        $statuses = [ReportStatus::Open, ReportStatus::Open, ReportStatus::Open, ReportStatus::Open, ReportStatus::Resolved, ReportStatus::Dismissed];

        foreach ($contents as $index => $content) {
            $reported = $content->random();
            $reporter = $this->residents->where('id', '!=', $reported->getAttribute('user_id'))->random();
            $status = $statuses[$index];

            Report::factory()->for($reporter)->for($reported, 'reportable')->create([
                'status' => $status,
                'resolved_at' => $status === ReportStatus::Open ? null : CarbonImmutable::now()->subDays($this->faker->numberBetween(1, 10)),
            ]);
        }
    }

    /**
     * Mostly published, as a live city looks, with the other statuses given, in random order.
     *
     * @param  list<ContentStatus>  $others
     * @return list<ContentStatus>
     */
    private function statuses(int $count, array $others): array
    {
        $statuses = [...$others, ...array_fill(0, $count - count($others), ContentStatus::Published)];

        return Random::elements($this->faker, $statuses, count($statuses));
    }
}
