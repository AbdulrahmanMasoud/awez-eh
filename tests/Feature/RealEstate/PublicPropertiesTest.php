<?php

declare(strict_types=1);

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\RealEstate\Actions\ShowRealEstateOptions;
use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Domains\RealEstate\Models\Property;
use App\Support\Geo\Point;
use Brick\Money\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->city = City::factory()->create();
    $this->district = District::factory()->for($this->city)->create();
});

/**
 * A published property in the test city, for sale unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function listed(array $attributes = []): Property
{
    return Property::factory()->published()
        ->recycle(test()->city)
        ->recycle(test()->district)
        ->create([
            'purpose' => PropertyPurpose::Sale,
            'rent_period' => null,
            'type' => PropertyType::Apartment,
            'price' => Money::of(1_000_000, 'EGP'),
            'area_m2' => 120,
            'bedrooms' => 3,
            'furnished' => false,
            'finishing' => Finishing::Finished,
            'brokered' => false,
            ...$attributes,
        ]);
}

/**
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function listedIds(array $query = []): array
{
    return test()->getJson('/api/v1/properties?'.http_build_query($query))->assertOk()->json('data.*.id');
}

it('lists published properties only, newest first', function (): void {
    $older = listed();
    $newer = listed();
    foreach ([ContentStatus::Draft, ContentStatus::PendingReview, ContentStatus::Paused, ContentStatus::Rejected, ContentStatus::Sold, ContentStatus::Archived] as $status) {
        Property::factory()->inStatus($status)->create();
    }

    expect(listedIds())->toBe([$newer->id, $older->id]);
});

it('pages by cursor, 20 at a time', function (): void {
    $properties = collect(range(1, 23))->map(fn (): Property => listed());

    $first = $this->getJson('/api/v1/properties')->assertOk();
    $cursor = $first->json('meta.next_cursor');
    $second = $this->getJson('/api/v1/properties?cursor='.$cursor)->assertOk();

    expect($first->json('data'))->toHaveCount(20)
        ->and($second->json('data'))->toHaveCount(3)
        ->and([...$first->json('data.*.id'), ...$second->json('data.*.id')])->toBe($properties->reverse()->pluck('id')->values()->all());
});

it('filters by each field', function (): void {
    $otherDistrict = District::factory()->for($this->city)->create();
    $sale = listed();
    $rent = listed(['purpose' => PropertyPurpose::Rent, 'rent_period' => RentPeriod::Monthly, 'price' => Money::of(6_500, 'EGP')]);
    $villa = listed(['type' => PropertyType::Villa, 'price' => Money::of(5_000_000, 'EGP'), 'area_m2' => 400, 'bedrooms' => 5]);
    $shop = listed(['type' => PropertyType::Shop, 'district_id' => $otherDistrict->id, 'bedrooms' => null, 'area_m2' => 40, 'finishing' => Finishing::SemiFinished]);
    $furnished = listed(['furnished' => true, 'price' => Money::of(1_500_000, 'EGP')]);
    $brokered = listed(['brokered' => true, 'price' => Money::of(2_000_000, 'EGP')]);

    expect(listedIds(['purpose' => 'rent']))->toBe([$rent->id])
        ->and(listedIds(['type' => ['villa', 'shop']]))->toBe([$shop->id, $villa->id])
        ->and(listedIds(['district_id' => [$otherDistrict->id]]))->toBe([$shop->id])
        ->and(listedIds(['price_min' => '1500000', 'price_max' => '2000000']))->toBe([$brokered->id, $furnished->id])
        ->and(listedIds(['price_max' => '6500.00']))->toBe([$rent->id])
        ->and(listedIds(['area_min' => 300]))->toBe([$villa->id])
        ->and(listedIds(['area_max' => 50]))->toBe([$shop->id])
        ->and(listedIds(['bedrooms_min' => 4]))->toBe([$villa->id])
        ->and(listedIds(['furnished' => 'true']))->toBe([$furnished->id])
        ->and(listedIds(['finishing' => 'semi_finished']))->toBe([$shop->id])
        ->and(listedIds(['brokered' => '1']))->toBe([$brokered->id])
        ->and(listedIds(['brokered' => 'false', 'type' => ['apartment'], 'purpose' => 'sale', 'furnished' => '0']))->toBe([$sale->id]);
});

it('reads prices in pounds without a float, to the piaster', function (): void {
    $exact = listed(['price' => Money::of('999999.99', 'EGP')]);
    listed(['price' => Money::of('1000000.00', 'EGP')]);

    expect(listedIds(['price_max' => '999999.99']))->toBe([$exact->id]);
});

it('sorts by price both ways and by distance, always ending on the id', function (): void {
    $centre = new Point(30.3811, 30.5266);
    $near = listed(['price' => Money::of(3_000_000, 'EGP'), 'location' => new Point(30.3812, 30.5267)]);
    $middle = listed(['price' => Money::of(1_000_000, 'EGP'), 'location' => new Point(30.3900, 30.5266)]);
    $far = listed(['price' => Money::of(2_000_000, 'EGP'), 'location' => new Point(30.4100, 30.5266)]);
    $sameAsMiddle = listed(['price' => Money::of(1_000_000, 'EGP'), 'location' => new Point(30.3900, 30.5266)]);

    expect(listedIds(['sort' => 'price_asc']))->toBe([$middle->id, $sameAsMiddle->id, $far->id, $near->id])
        ->and(listedIds(['sort' => 'price_desc']))->toBe([$near->id, $far->id, $sameAsMiddle->id, $middle->id])
        ->and(listedIds(['sort' => 'nearest', 'lat' => $centre->latitude, 'lng' => $centre->longitude, 'radius_m' => 20_000]))
        ->toBe([$near->id, $middle->id, $sameAsMiddle->id, $far->id]);
});

it('pages the nearest sort by cursor too', function (): void {
    $properties = collect(range(1, 22))->map(fn (int $step): Property => listed(['location' => new Point(30.3811 + $step / 10_000, 30.5266)]));
    $query = ['sort' => 'nearest', 'lat' => 30.3811, 'lng' => 30.5266];

    $first = $this->getJson('/api/v1/properties?'.http_build_query($query))->assertOk();
    $second = $this->getJson('/api/v1/properties?'.http_build_query([...$query, 'cursor' => $first->json('meta.next_cursor')]))->assertOk();

    expect([...$first->json('data.*.id'), ...$second->json('data.*.id')])->toBe($properties->pluck('id')->all());
});

it('keeps only what is within the radius, 5 km by default', function (): void {
    $inside = listed(['location' => new Point(30.3811 + 4_000 / 110_858.6, 30.5266)]);
    $outside = listed(['location' => new Point(30.3811 + 6_000 / 110_858.6, 30.5266)]);
    $location = ['lat' => 30.3811, 'lng' => 30.5266];

    expect(listedIds($location))->toBe([$inside->id])
        ->and(listedIds([...$location, 'radius_m' => 7_000]))->toBe([$outside->id, $inside->id]);
});

it('refuses filters it cannot read', function (array $query, string $field): void {
    $this->getJson('/api/v1/properties?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($field);
})->with([
    'nearest without a location' => [['sort' => 'nearest'], 'lat'],
    'a latitude alone' => [['lat' => 30.38], 'lng'],
    'a radius past 20 km' => [['lat' => 30.38, 'lng' => 30.52, 'radius_m' => 20_001], 'radius_m'],
    'a price with three decimals' => [['price_min' => '10.005'], 'price_min'],
    'a maximum under the minimum' => [['price_min' => '100', 'price_max' => '50'], 'price_max'],
    'an unknown type' => [['type' => ['castle']], 'type.0'],
    'an unknown sort' => [['sort' => 'cheapest'], 'sort'],
    'a district that is not an id' => [['district_id' => ['second']], 'district_id.0'],
    'a word for a boolean' => [['furnished' => 'yes'], 'furnished'],
]);

it('shows the location rounded to about 100 m, and exact to its owner only', function (): void {
    $owner = User::factory()->create();
    $property = listed(['user_id' => $owner->id, 'location' => new Point(30.371234, 30.508765)]);

    $this->getJson("/api/v1/properties/{$property->id}")
        ->assertOk()
        ->assertJsonPath('data.location', ['lat' => 30.371, 'lng' => 30.509, 'exact' => false]);
    $this->getJson('/api/v1/properties')->assertJsonPath('data.0.location.lat', 30.371);

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/properties/{$property->id}")
        ->assertJsonPath('data.location', ['lat' => 30.371234, 'lng' => 30.508765, 'exact' => true]);
});

it('shows a property with what a resident needs and the advertiser, never their number', function (): void {
    Date::setTestNow('2026-11-27 12:00:00');
    $owner = User::factory()->withPhone('01012345678')->create(['name' => 'أحمد السيد', 'created_at' => '2026-11-20 08:00:00']);
    $property = listed(['user_id' => $owner->id, 'title' => 'شقة للبيع في الحي الأول', 'price' => Money::of('1250000.50', 'EGP')]);

    $response = $this->getJson("/api/v1/properties/{$property->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'شقة للبيع في الحي الأول')
        ->assertJsonPath('data.price', ['amount' => '1250000.50', 'currency' => 'EGP'])
        ->assertJsonPath('data.district.name', $this->district->name)
        ->assertJsonPath('data.advertiser', [
            'is_platform' => false,
            'name' => 'أحمد السيد',
            'avatar_url' => null,
            'member_since' => '2026-11-20',
            'has_confirmed_phone' => true,
        ])
        ->assertJsonMissingPath('data.status')
        ->assertJsonMissingPath('data.user_id');

    expect($response->getContent())->not->toContain('1012345678');
});

it('answers 404 for what is not published, unless it is the owner\'s own', function (ContentStatus $status): void {
    $owner = User::factory()->create();
    $property = Property::factory()->for($owner)->inStatus($status)->create();

    $this->getJson("/api/v1/properties/{$property->id}")->assertNotFound()->assertJsonPath('code', 'not_found');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/properties/{$property->id}")->assertNotFound();

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/properties/{$property->id}")->assertOk();
})->with([ContentStatus::Draft, ContentStatus::PendingReview, ContentStatus::Paused, ContentStatus::Rejected, ContentStatus::Sold, ContentStatus::Rented, ContentStatus::Archived]);

it('answers 404 for an id that is not one', function (): void {
    $this->getJson('/api/v1/properties/not-an-id')->assertNotFound();
    $this->getJson('/api/v1/properties/019ac2f1-7a3e-7d10-9b2c-4e5f6a7b8c9d')->assertNotFound();
});

it('counts a view once a day per account or address, never the owner\'s', function (): void {
    Date::setTestNow('2026-11-27 09:00:00');
    $owner = User::factory()->create();
    $property = listed(['user_id' => $owner->id, 'views_count' => 0]);
    $updatedAt = $property->updated_at?->toDateTimeString();
    $view = fn (string $ip = '41.33.1.1') => $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson("/api/v1/properties/{$property->id}")->assertOk();

    $view();
    $view();
    expect($property->refresh()->views_count)->toBe(1);

    $view('41.33.1.2');
    Sanctum::actingAs(User::factory()->create());
    $view();
    $view();
    expect($property->refresh()->views_count)->toBe(3);

    Sanctum::actingAs($owner);
    $view();
    expect($property->refresh()->views_count)->toBe(3);

    Date::setTestNow('2026-11-28 09:00:00');
    Sanctum::actingAs(User::factory()->create());
    $view();
    expect($property->refresh()->views_count)->toBe(4)
        // A view is not an edit.
        ->and($property->updated_at?->toDateTimeString())->toBe($updatedAt);
});

it('gives the filters\' options with both labels, cached until a district changes', function (): void {
    $response = $this->getJson('/api/v1/real-estate/options')->assertOk();

    expect($response->json('data.purposes'))->toBe([
        ['value' => 'sale', 'label_ar' => 'للبيع', 'label_en' => 'For sale'],
        ['value' => 'rent', 'label_ar' => 'للإيجار', 'label_en' => 'For rent'],
    ])
        ->and($response->json('data.types.*.value'))->toBe(array_column(PropertyType::cases(), 'value'))
        ->and($response->json('data.finishings.*.value'))->toBe(array_column(Finishing::cases(), 'value'))
        ->and($response->json('data.rent_periods.*.value'))->toBe(['monthly', 'yearly'])
        ->and($response->json('data.districts.*.id'))->toBe([$this->district->id])
        ->and(Cache::has(ShowRealEstateOptions::CACHE_KEY))->toBeTrue();

    $added = District::factory()->for($this->city)->create(['sort_order' => 99]);

    expect(Cache::has(ShowRealEstateOptions::CACHE_KEY))->toBeFalse()
        ->and($this->getJson('/api/v1/real-estate/options')->json('data.districts.*.id'))->toBe([$this->district->id, $added->id]);

    $added->update(['name_ar' => 'الحي الجديد']);

    expect($this->getJson('/api/v1/real-estate/options')->json('data.districts.1.name_ar'))->toBe('الحي الجديد');
});

it('answers in English labels too, whatever the request\'s language', function (): void {
    $this->getJson('/api/v1/real-estate/options', ['Accept-Language' => 'en'])
        ->assertJsonPath('data.types.0', ['value' => 'apartment', 'label_ar' => 'شقة', 'label_en' => 'Apartment']);
});
