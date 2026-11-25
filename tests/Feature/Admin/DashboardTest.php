<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Services\Models\Provider;
use App\Filament\Dashboard\DashboardFigures;
use App\Filament\Dashboard\DashboardPeriod;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\OverviewStats;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    // 20:30 in Cairo (UTC+2 in November).
    Date::setTestNow('2026-11-25 18:30:00');
});

it('starts today at midnight in Cairo, and the week and month 7 and 30 days back', function (): void {
    $now = Date::now();

    expect(DashboardPeriod::Today->start($now)->toDateTimeString())->toBe('2026-11-24 22:00:00')
        ->and(DashboardPeriod::Week->start($now)->toDateTimeString())->toBe('2026-11-18 18:30:00')
        ->and(DashboardPeriod::Month->start($now)->toDateTimeString())->toBe('2026-10-26 18:30:00')
        ->and(DashboardPeriod::fromFilter('nonsense'))->toBe(DashboardPeriod::Month)
        ->and(DashboardPeriod::fromFilter(null))->toBe(DashboardPeriod::Month);
});

it('counts new users per period, with the share that confirmed a phone', function (): void {
    User::factory()->withPhone()->create(['created_at' => '2026-11-25 10:00:00']);
    User::factory()->create(['created_at' => '2026-11-25 11:00:00']);
    User::factory()->create(['created_at' => '2026-11-24 21:00:00']); // 23:00 Cairo, yesterday
    User::factory()->withPhone()->create(['created_at' => '2026-11-20 10:00:00']);
    User::factory()->create(['created_at' => '2026-10-01 10:00:00']);

    $figures = new DashboardFigures;

    expect($figures->newUsers(DashboardPeriod::Today))->toBe(['count' => 2, 'confirmed_percent' => 50])
        ->and($figures->newUsers(DashboardPeriod::Week))->toBe(['count' => 4, 'confirmed_percent' => 50])
        ->and($figures->newUsers(DashboardPeriod::Month))->toBe(['count' => 4, 'confirmed_percent' => 50]);
});

it('counts listings published per period, by kind', function (): void {
    Property::factory()->published()->create(['published_at' => '2026-11-25 09:00:00']);
    Provider::factory()->published()->create(['published_at' => '2026-11-22 09:00:00']);
    Place::factory()->published()->create(['published_at' => '2026-11-10 09:00:00']);
    UsedItem::factory()->published()->create(['published_at' => '2026-09-01 09:00:00']);
    Property::factory()->create(); // pending review

    $figures = new DashboardFigures;

    expect($figures->published(DashboardPeriod::Today))->toBe(['properties' => 1, 'providers' => 0, 'places' => 0, 'used_items' => 0, 'total' => 1])
        ->and($figures->published(DashboardPeriod::Week))->toBe(['properties' => 1, 'providers' => 1, 'places' => 0, 'used_items' => 0, 'total' => 2])
        ->and($figures->published(DashboardPeriod::Month))->toBe(['properties' => 1, 'providers' => 1, 'places' => 1, 'used_items' => 0, 'total' => 3]);
});

it('counts the whole review queue, whatever the period, with its oldest item', function (): void {
    Property::factory()->create(['created_at' => '2026-11-25 12:30:00']);
    UsedItem::factory()->create(['created_at' => '2026-11-01 12:00:00']);
    Place::factory()->published()->create();

    expect(new DashboardFigures()->pendingReview())->toEqual(['count' => 2, 'oldest' => Date::parse('2026-11-01 12:00:00')]);
});

it('shows the figures on the dashboard for the chosen period', function (): void {
    actingAsAdmin();
    Property::factory()->create(['created_at' => '2026-11-25 12:30:00']);
    Property::factory()->published()->create(['published_at' => '2026-11-20 09:00:00']);

    Livewire::test(OverviewStats::class, ['pageFilters' => ['period' => 'week']])
        ->assertSee('مستني مراجعة')
        ->assertSee('أقدم واحد من 6 ساعات')
        ->assertSee('إعلانات منشورة')
        ->assertSee('1 عقار');

    Livewire::test(OverviewStats::class, ['pageFilters' => ['period' => 'today']])
        ->assertSee('0 عقار');
});

it('greets the admin by first name, with the date in Cairo and what is waiting', function (): void {
    actingAsAdmin();
    Property::factory()->count(3)->create();

    $this->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('مساء الخير يا')
        ->assertSee('عندك 3 حاجة مستنية قرارك');
});
