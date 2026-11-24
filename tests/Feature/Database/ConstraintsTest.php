<?php

declare(strict_types=1);

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Places\Models\OpeningHour;
use App\Domains\Places\Models\Place;
use App\Domains\Places\Models\PlaceCategory;
use App\Domains\Places\Models\PlaceOffer;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\Requests\Enums\RequestArea;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Provider;
use App\Domains\Services\Models\ProviderPrice;
use App\Domains\Services\Models\Trade;
use Brick\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('refuses rows the data model forbids', function (Closure $write, string $constraint): void {
    // Each write runs in a savepoint, so the failed statement does not end the test's transaction.
    expect(fn () => DB::transaction($write))->toThrow(QueryException::class, $constraint);
})->with([
    'a rent without a period' => [
        fn () => Property::factory()->create(['purpose' => PropertyPurpose::Rent, 'rent_period' => null]),
        'properties_rent_period_required_check',
    ],
    'a sale with a rent period' => [
        fn () => Property::factory()->create(['purpose' => PropertyPurpose::Sale, 'rent_period' => RentPeriod::Monthly]),
        'properties_rent_period_required_check',
    ],
    'an agreement with no commission' => [
        fn () => PropertyAgreement::factory()->create(['commission_rate_basis_points' => null, 'commission_fixed' => null]),
        'property_agreements_one_commission_check',
    ],
    'an agreement with both a rate and a fixed commission' => [
        fn () => PropertyAgreement::factory()->create(['commission_rate_basis_points' => 250, 'commission_fixed' => Money::of(20000, 'EGP')]),
        'property_agreements_one_commission_check',
    ],
    'an agreement that ends before it is signed' => [
        fn () => PropertyAgreement::factory()->create(['signed_on' => '2026-11-01', 'ends_on' => '2026-10-01']),
        'property_agreements_ends_after_signing_check',
    ],
    'two primary trades for one craftsman' => [
        function (): void {
            $provider = Provider::factory()->create();
            $provider->trades()->attach(Trade::factory()->create(), ['is_primary' => true]);
            $provider->trades()->attach(Trade::factory()->create(), ['is_primary' => true]);
        },
        'provider_trades_one_primary_index',
    ],
    'a second profile for one craftsman' => [
        function (): void {
            $provider = Provider::factory()->create();
            Provider::factory()->for($provider->user)->create();
        },
        'providers_user_id_unique',
    ],
    'a price range that ends below its start' => [
        fn () => ProviderPrice::factory()->create(['price_from' => Money::of(500, 'EGP'), 'price_to' => Money::of(300, 'EGP')]),
        'provider_prices_range_check',
    ],
    'two districts with one slug in a city' => [
        function (): void {
            $city = City::factory()->create();
            District::factory()->for($city)->create(['slug' => 'residential-1']);
            District::factory()->for($city)->create(['slug' => 'residential-1']);
        },
        'districts_city_id_slug_unique',
    ],
    'a weekday past Saturday' => [
        fn () => OpeningHour::factory()->create(['weekday' => 7]),
        'opening_hours_weekday_check',
    ],
    'an offer that ends when it starts' => [
        fn () => PlaceOffer::factory()->create(['starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-01 10:00:00']),
        'place_offers_ends_after_start_check',
    ],
    'a trade on a request that is not for a craftsman' => [
        fn () => ServiceRequest::factory()->create(['area' => RequestArea::RealEstate, 'trade_id' => Trade::factory()->create()->id]),
        'requests_trade_area_check',
    ],
    'a place category on a request that is not for a place' => [
        fn () => ServiceRequest::factory()->create(['area' => RequestArea::Services, 'place_category_id' => PlaceCategory::factory()->create()->id]),
        'requests_place_category_area_check',
    ],
    // The enum cast refuses it in PHP; the CHECK refuses it from any other writer.
    'a status no enum case has, written past the model' => [
        function (): void {
            $place = Place::factory()->create();
            DB::table('places')->where('id', $place->id)->update(['status' => 'approved']);
        },
        'places_status_check',
    ],
]);

it('allows one district slug in two cities', function (): void {
    District::factory()->for(City::factory())->create(['slug' => 'residential-1']);
    District::factory()->for(City::factory())->create(['slug' => 'residential-1']);

    expect(District::query()->where('slug', 'residential-1')->count())->toBe(2);
});

it('allows a craftsman several trades, one of them primary', function (): void {
    $provider = Provider::factory()->create();
    $provider->trades()->attach(Trade::factory()->create(), ['is_primary' => true]);
    $provider->trades()->attach(Trade::factory()->count(2)->create()->modelKeys(), ['is_primary' => false]);

    expect($provider->trades()->count())->toBe(3)
        ->and($provider->trades()->wherePivot('is_primary', true)->count())->toBe(1);
});
