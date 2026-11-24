<?php

declare(strict_types=1);

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Models\Report;
use App\Domains\Places\Models\Place;
use App\Domains\Places\Models\PlaceCategory;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Provider;
use App\Domains\Services\Models\Trade;
use App\Support\Geo\Point;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;

it('seeds Sadat City with its districts, the trades and the place categories', function (): void {
    $this->seed(ReferenceDataSeeder::class);

    $city = City::query()->where('slug', 'sadat-city')->sole();

    expect($city->name_ar)->toBe('مدينة السادات')
        ->and($city->name_en)->toBe('Sadat City')
        ->and($city->is_active)->toBeTrue()
        ->and($city->center?->equals(new Point(30.3811, 30.5266)))->toBeTrue()
        ->and($city->districts()->orderBy('sort_order')->pluck('name_ar')->all())->toBe([
            'المنطقة السكنية الأولى',
            'المنطقة السكنية الثانية',
            'المنطقة السكنية الرابعة',
            'المنطقة السكنية السابعة',
            'المنطقة السكنية الثامنة',
            'المنطقة السكنية الحادية عشرة',
            'المنطقة الصناعية',
        ])
        ->and(District::query()->whereNotNull('center')->count())->toBe(0)
        ->and(Trade::query()->orderBy('sort_order')->pluck('name_ar')->all())->toBe([
            'سباك', 'كهربائي', 'نجار', 'نقاش', 'محارة', 'سيراميك', 'ألوميتال',
            'تكييف وتبريد', 'صيانة أجهزة', 'حداد', 'عامل نظافة', 'نقل عفش', 'مدرس خصوصي',
        ])
        ->and(PlaceCategory::query()->whereNull('parent_id')->orderBy('sort_order')->pluck('name_ar')->all())->toBe([
            'مطاعم', 'صيدليات', 'سوبر ماركت', 'مخابز', 'ملابس', 'موبايلات', 'أدوات منزلية', 'مستلزمات بناء',
        ])
        ->and(PlaceCategory::query()->where('slug', 'restaurants')->sole()->children()->orderBy('sort_order')->pluck('name_ar')->all())
        ->toBe(['مأكولات شعبية', 'مشويات', 'بيتزا', 'كافيهات']);
});

it('gives every reference row an English name and a slug', function (): void {
    $this->seed(ReferenceDataSeeder::class);

    foreach ([City::class, District::class, Trade::class, PlaceCategory::class] as $model) {
        expect($model::query()->where('name_en', '')->orWhere('slug', '')->count())->toBe(0);
    }
});

it('seeds reference data again without duplicating it, restoring what changed', function (): void {
    $this->seed(ReferenceDataSeeder::class);
    $counts = fn (): array => array_map(fn (string $model): int => $model::query()->count(), [City::class, District::class, Trade::class, PlaceCategory::class]);
    $before = $counts();
    Trade::query()->where('slug', 'plumber')->update(['name_ar' => 'سباك قديم']);

    $this->seed(ReferenceDataSeeder::class);

    expect($counts())->toBe($before)
        ->and(Trade::query()->where('slug', 'plumber')->value('name_ar'))->toBe('سباك');
});

it('seeds no demo content in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    // As a deploy runs it: in production, db:seed asks for confirmation without --force.
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(City::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(0)
        ->and(Property::query()->count())->toBe(0);
});

it('seeds the demo content the brief asks for in local and testing', function (): void {
    $this->seed(DatabaseSeeder::class);

    $published = fn (string $model): float => $model::query()->where('status', ContentStatus::Published)->count() / $model::query()->count();

    expect(Property::query()->count())->toBe(30)
        ->and(Property::query()->where('brokered', true)->count())->toBe(10)
        ->and(PropertyAgreement::query()->count())->toBe(1)
        ->and(PropertyAgreement::query()->sole()->property?->brokered)->toBeTrue()
        ->and(Provider::query()->count())->toBe(40)
        ->and(Provider::query()->whereDoesntHave('trades')->count())->toBe(0)
        ->and(DB::table('provider_trades')->where('is_primary', true)->count())->toBe(40)
        ->and(Trade::query()->whereDoesntHave('providers')->count())->toBe(0)
        ->and(Place::query()->count())->toBe(40)
        ->and(Place::query()->whereDoesntHave('items')->count())->toBe(0)
        ->and(Place::query()->whereDoesntHave('openingHours')->count())->toBe(0)
        ->and(Place::query()->whereDoesntHave('offers')->count())->toBe(0)
        ->and(UsedItem::query()->count())->toBe(60)
        ->and(ServiceRequest::query()->count())->toBe(20)
        ->and(ServiceRequest::query()->whereDoesntHave('replies')->count())->toBe(0)
        ->and(RequestReply::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(Report::query()->count())->toBeGreaterThanOrEqual(3)
        // 30 residents own the content; each of the 40 craftsmen has an account of their own.
        ->and(User::query()->whereDoesntHave('provider')->count())->toBe(30)
        ->and(User::query()->count())->toBe(70);

    foreach ([Property::class, Provider::class, Place::class, UsedItem::class] as $model) {
        expect($published($model))->toBeGreaterThan(0.6)->toBeLessThan(1.0);
    }
});

it('places the demo content within about 4 km of the city centre', function (): void {
    $this->seed(DatabaseSeeder::class);

    foreach (['properties', 'providers', 'places', 'used_items', 'requests'] as $table) {
        $outside = DB::table($table)
            ->whereNotNull('location')
            ->whereRaw('not ST_DWithin(location, ST_SetSRID(ST_MakePoint(30.5266, 30.3811), 4326)::geography, 4100)')
            ->count();

        expect($outside)->toBe(0, "{$table} has rows outside the city");
    }
});

it('refuses to seed demo content outside local and testing, even when called directly', function (): void {
    $this->seed(ReferenceDataSeeder::class);
    app()->detectEnvironment(fn (): string => 'staging');

    expect(fn () => $this->seed(DemoContentSeeder::class))
        ->toThrow(LogicException::class, 'local and testing only');
});
