<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Enums\UserLocale;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\RealEstate\Models\Property;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ReviewQueue;
use App\Filament\Review\ReviewKinds;
use App\Filament\Widgets\PendingReview;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->withoutVite();
});

it('opens المراجعة for moderators and super admins only', function (AdminRole $role, int $status): void {
    actingAsAdmin($role);

    $this->get(ReviewQueue::getUrl())->assertStatus($status);
})->with([
    [AdminRole::SuperAdmin, 200],
    [AdminRole::Moderator, 200],
    [AdminRole::Sales, 403],
    [AdminRole::DataEntry, 403],
]);

it('lists what waits for review, oldest first, and nothing else', function (): void {
    actingAsAdmin(AdminRole::Moderator);
    $newer = Property::factory()->create(['created_at' => Date::now()->subHour(), 'title' => 'شقة جديدة في الانتظار']);
    $older = Property::factory()->create(['created_at' => Date::now()->subDays(2), 'title' => 'فيلا قديمة في الانتظار']);
    $published = Property::factory()->published()->create(['title' => 'محل منشور من زمان']);

    Livewire::test(ReviewQueue::class)
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
        ->assertCanNotSeeTableRecords([$published])
        ->assertSee('فيلا قديمة في الانتظار');
});

it('approves in one click, through the Action, into the activity log', function (): void {
    $moderator = actingAsAdmin(AdminRole::Moderator);
    $property = Property::factory()->create();

    Livewire::test(ReviewQueue::class)
        ->callAction(TestAction::make('approve')->table($property))
        ->assertHasNoErrors()
        ->assertCanNotSeeTableRecords([$property]);

    expect($property->refresh()->status)->toBe(ContentStatus::Published)
        ->and(Activity::query()->where('log_name', AdminLog::NAME)->where('event', 'approve')->sole()->causer?->is($moderator))->toBeTrue();
});

it('rejects with a required reason, written in the owner\'s language', function (): void {
    actingAsAdmin(AdminRole::Moderator);
    $arabic = Property::factory()->create();
    $english = Property::factory()->for(User::factory()->state(['locale' => UserLocale::English]))->create();

    Livewire::test(ReviewQueue::class)
        ->callAction(TestAction::make('reject')->table($arabic), ['reason' => null])
        ->assertHasFormErrors(['reason' => 'required']);

    Livewire::test(ReviewQueue::class)
        ->callAction(TestAction::make('reject')->table($arabic), ['reason' => 'other', 'details' => ''])
        ->assertHasFormErrors(['details' => 'required']);

    Livewire::test(ReviewQueue::class)
        ->callAction(TestAction::make('reject')->table($arabic), ['reason' => 'unclear_photos', 'details' => 'صوّر الصالة في النهار.'])
        ->assertHasNoFormErrors();
    Livewire::test(ReviewQueue::class)
        ->callAction(TestAction::make('reject')->table($english), ['reason' => 'duplicate'])
        ->assertHasNoFormErrors();

    expect($arabic->refresh()->status)->toBe(ContentStatus::Rejected)
        ->and($arabic->rejection_reason)->toBe('الصور مش واضحة. صوّر الصالة في النهار.')
        ->and($english->refresh()->rejection_reason)->toBe('The listing is a duplicate.');
});

it('badges the sidebar with the size of the queue', function (): void {
    actingAsAdmin(AdminRole::Moderator);
    Property::factory()->count(3)->create();
    Property::factory()->published()->create();

    expect(ReviewQueue::getNavigationBadge())->toBe('3')
        ->and(ReviewQueue::getNavigationBadgeColor())->toBe('warning');
});

it('offers the kinds an admin may review, and none to others', function (): void {
    $moderator = actingAsAdmin(AdminRole::Moderator);
    $sales = User::factory()->admin(AdminRole::Sales)->create();

    expect(array_keys(resolve(ReviewKinds::class)->for($moderator)))->toBe(['properties'])
        ->and(resolve(ReviewKinds::class)->for($sales))->toBe([]);
});

it('shows محتاج قرارك on the dashboard to those who review, five rows at most', function (): void {
    actingAsAdmin(AdminRole::Moderator);
    $properties = Property::factory()->count(6)->sequence(fn ($sequence): array => ['created_at' => Date::now()->subHours(10 - $sequence->index)])->create();

    Livewire::test(PendingReview::class)
        ->assertCanSeeTableRecords($properties->take(5), inOrder: true)
        ->assertCanNotSeeTableRecords($properties->slice(5))
        ->callAction(TestAction::make('approve')->table($properties->first()))
        ->assertHasNoErrors();

    expect($properties->first()->refresh()->status)->toBe(ContentStatus::Published);

    $this->get(Dashboard::getUrl())->assertOk()->assertSeeLivewire(PendingReview::class);
});

it('keeps محتاج قرارك off the dashboard of those who do not review', function (): void {
    actingAsAdmin(AdminRole::Sales);

    expect(PendingReview::canView())->toBeFalse();
    $this->get(Dashboard::getUrl())->assertOk()->assertDontSeeLivewire(PendingReview::class);
});
