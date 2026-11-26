<?php

declare(strict_types=1);

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Places\Models\PlaceCategory;
use App\Domains\Services\Models\Trade;
use App\Filament\Resources\Cities\CityResource;
use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Filament\Resources\Cities\RelationManagers\DistrictsRelationManager;
use App\Filament\Resources\Districts\DistrictResource;
use App\Filament\Resources\Districts\Pages\CreateDistrict;
use App\Filament\Resources\PlaceCategories\Pages\CreatePlaceCategory;
use App\Filament\Resources\PlaceCategories\PlaceCategoryResource;
use App\Filament\Resources\Trades\Pages\EditTrade;
use App\Filament\Resources\Trades\Pages\ListTrades;
use App\Filament\Resources\Trades\TradeResource;
use App\Support\Geo\Point;
use Filament\Actions\Testing\TestAction;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * @return array<string, array{class-string<resource>, Closure(): Model}>
 */
function referenceResources(): array
{
    return [
        'cities' => [CityResource::class, fn (): Model => City::factory()->create()],
        'districts' => [DistrictResource::class, fn (): Model => District::factory()->create()],
        'trades' => [TradeResource::class, fn (): Model => Trade::factory()->create()],
        'place categories' => [PlaceCategoryResource::class, fn (): Model => PlaceCategory::factory()->create()],
    ];
}

it('lets data entry and super admins list, create and edit reference data, and refuses the other roles', function (string $resource, Closure $record, AdminRole $role, bool $allowed): void {
    actingAsAdmin($role);
    $row = $record();
    $status = $allowed ? 200 : 403;

    $this->get($resource::getUrl('index'))->assertStatus($status);
    $this->get($resource::getUrl('create'))->assertStatus($status);
    $this->get($resource::getUrl('edit', ['record' => $row]))->assertStatus($status);
})->with(fn (): array => array_merge(...array_map(
    fn (string $name, array $resource): array => [
        "{$name}, super_admin" => [...$resource, AdminRole::SuperAdmin, true],
        "{$name}, data_entry" => [...$resource, AdminRole::DataEntry, true],
        "{$name}, moderator" => [...$resource, AdminRole::Moderator, false],
        "{$name}, sales" => [...$resource, AdminRole::Sales, false],
    ],
    array_keys(referenceResources()),
    referenceResources(),
)));

it('offers no delete anywhere: reference data is deactivated (DECISIONS 42)', function (string $resource, Closure $record): void {
    $admin = actingAsAdmin();
    $row = $record();

    expect($admin->can('delete', $row))->toBeFalse()
        ->and($admin->can('deleteAny', $row::class))->toBeFalse();
})->with(referenceResources());

it('creates a city from its coordinates', function (): void {
    actingAsAdmin(AdminRole::DataEntry);

    Livewire::test(CreateCity::class)
        ->fillForm(['name_ar' => 'مدينة بدر', 'name_en' => 'Badr City', 'slug' => 'badr-city', 'is_active' => true, 'latitude' => 30.1373, 'longitude' => 31.7146])
        ->call('create')
        ->assertHasNoFormErrors();

    $city = City::query()->where('slug', 'badr-city')->sole();

    expect($city->center)->toEqual(new Point(30.1373, 31.7146))
        ->and($city->name_en)->toBe('Badr City');
});

it('shows a city\'s coordinates when it is edited, and deactivates it', function (): void {
    actingAsAdmin(AdminRole::DataEntry);
    $city = City::factory()->create(['center' => new Point(30.37, 30.53)]);

    Livewire::test(EditCity::class, ['record' => $city->getRouteKey()])
        ->assertSchemaStateSet(['latitude' => 30.37, 'longitude' => 30.53])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($city->fresh()?->is_active)->toBeFalse();
});

it('refuses a city without coordinates or with impossible ones', function (array $coordinates, array $wrong): void {
    actingAsAdmin(AdminRole::DataEntry);

    Livewire::test(CreateCity::class)
        ->fillForm(['name_ar' => 'مدينة', 'name_en' => 'City', 'slug' => 'city', ...$coordinates])
        ->call('create')
        ->assertHasFormErrors($wrong);
})->with([
    'none' => [['latitude' => null, 'longitude' => null], ['latitude', 'longitude']],
    'latitude past 90' => [['latitude' => 91, 'longitude' => 31], ['latitude']],
    'longitude past 180' => [['latitude' => 30, 'longitude' => 181], ['longitude']],
]);

it('lists a city\'s districts under it and adds one there', function (): void {
    actingAsAdmin(AdminRole::DataEntry);
    $city = City::factory()->create();
    $district = District::factory()->for($city)->create();

    Livewire::test(DistrictsRelationManager::class, ['ownerRecord' => $city, 'pageClass' => EditCity::class])
        ->assertCanSeeTableRecords([$district])
        ->callAction(TestAction::make('create')->table(), ['name_ar' => 'الحي الثاني عشر', 'name_en' => '12th District', 'slug' => 'district-12'])
        ->assertHasNoFormErrors();

    expect($city->districts()->where('slug', 'district-12')->exists())->toBeTrue();
});

it('keeps a district\'s slug unique within its city only', function (): void {
    actingAsAdmin(AdminRole::DataEntry);
    $taken = District::factory()->create(['slug' => 'industrial-zone']);
    $otherCity = City::factory()->create();

    Livewire::test(CreateDistrict::class)
        ->fillForm(['city_id' => $taken->city_id, 'name_ar' => 'المنطقة الصناعية', 'name_en' => 'Industrial zone', 'slug' => 'industrial-zone'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);

    Livewire::test(CreateDistrict::class)
        ->fillForm(['city_id' => $otherCity->id, 'name_ar' => 'المنطقة الصناعية', 'name_en' => 'Industrial zone', 'slug' => 'industrial-zone'])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('reorders trades by dragging, for roles that may', function (AdminRole $role, bool $allowed): void {
    actingAsAdmin($role);
    [$first, $second] = [Trade::factory()->create(['sort_order' => 1]), Trade::factory()->create(['sort_order' => 2])];

    if (! $allowed) {
        $this->get(TradeResource::getUrl('index'))->assertForbidden();

        return;
    }

    Livewire::test(ListTrades::class)
        ->call('toggleTableReordering')
        ->call('reorderTable', [$second->getKey(), $first->getKey()]);

    expect($second->fresh()?->sort_order)->toBe(1)
        ->and($first->fresh()?->sort_order)->toBe(2);
})->with([
    [AdminRole::DataEntry, true],
    [AdminRole::Sales, false],
]);

it('takes a Lucide icon from the list only', function (string $icon, bool $valid): void {
    actingAsAdmin(AdminRole::DataEntry);
    $trade = Trade::factory()->create();

    $test = Livewire::test(EditTrade::class, ['record' => $trade->getRouteKey()])
        ->fillForm(['icon' => $icon])
        ->call('save');

    $valid ? $test->assertHasNoFormErrors() : $test->assertHasFormErrors(['icon']);
})->with([
    ['paint-roller', true],
    ['not-an-icon', false],
]);

it('lets a subcategory have a top-level parent only', function (): void {
    actingAsAdmin(AdminRole::DataEntry);
    $parent = PlaceCategory::factory()->create(['parent_id' => null]);

    Livewire::test(CreatePlaceCategory::class)
        ->fillForm(['name_ar' => 'صيدليات ٢٤ ساعة', 'name_en' => '24h pharmacies', 'slug' => 'pharmacies-24h', 'parent_id' => $parent->id, 'icon' => 'pill', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PlaceCategory::query()->where('slug', 'pharmacies-24h')->sole()->parent_id)->toBe($parent->id);
});

it('lists cities for a data entry admin', function (): void {
    actingAsAdmin(AdminRole::DataEntry);
    $cities = City::factory()->count(2)->create();

    Livewire::test(ListCities::class)->assertCanSeeTableRecords($cities);
});
