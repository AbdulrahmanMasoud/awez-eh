<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\DeviceToken;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\RealEstate\Models\Property;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use Filament\Livewire\GlobalSearch;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows users to every role that may, and to nobody else', function (AdminRole $role, int $status): void {
    actingAsAdmin($role);
    $resident = User::factory()->create();

    $this->get(UserResource::getUrl('index'))->assertStatus($status);
    $this->get(UserResource::getUrl('view', ['record' => $resident]))->assertStatus($status);
})->with([
    [AdminRole::SuperAdmin, 200],
    [AdminRole::Moderator, 200],
    [AdminRole::Sales, 200],
    [AdminRole::DataEntry, 403],
]);

it('has no create, edit or delete page: residents own their accounts', function (): void {
    $admin = actingAsAdmin();
    $resident = User::factory()->create();

    expect(UserResource::getPages())->toHaveKeys(['index', 'view'])->not->toHaveKeys(['create', 'edit'])
        ->and($admin->can('update', $resident))->toBeFalse()
        ->and($admin->can('delete', $resident))->toBeFalse();
});

it('lists numbers masked, never in full', function (): void {
    actingAsAdmin();
    User::factory()->withPhone('01012345678')->create(['name' => 'منى عادل']);

    $this->get(UserResource::getUrl('index'))
        ->assertOk()
        ->assertSee('010*****678')
        ->assertDontSee('01012345678')
        ->assertDontSee('+201012345678');
});

it('filters users by status, confirmed phone, craftsman, advertiser and admin', function (): void {
    $admin = actingAsAdmin();
    $suspended = User::factory()->suspended()->create();
    $withPhone = User::factory()->withPhone()->create();
    $advertiser = User::factory()->create();
    Property::factory()->for($advertiser)->create();

    Livewire::test(ListUsers::class)
        ->filterTable('status', 'suspended')
        ->assertCanSeeTableRecords([$suspended])
        ->assertCanNotSeeTableRecords([$withPhone, $advertiser]);

    Livewire::test(ListUsers::class)
        ->filterTable('has_phone', true)
        ->assertCanSeeTableRecords([$withPhone])
        ->assertCanNotSeeTableRecords([$suspended, $advertiser]);

    Livewire::test(ListUsers::class)
        ->filterTable('is_advertiser', true)
        ->assertCanSeeTableRecords([$advertiser])
        ->assertCanNotSeeTableRecords([$withPhone]);

    Livewire::test(ListUsers::class)
        ->filterTable('is_admin', true)
        ->assertCanSeeTableRecords([$admin])
        ->assertCanNotSeeTableRecords([$withPhone, $advertiser]);
});

it('finds a user in the table by a number typed with spaces, or by email', function (): void {
    actingAsAdmin();
    $mona = User::factory()->withPhone('01012345678')->create(['email' => 'mona@example.com']);
    $other = User::factory()->withPhone('01112345678')->create();

    Livewire::test(ListUsers::class)
        ->searchTable('0101 234 5678')
        ->assertCanSeeTableRecords([$mona])
        ->assertCanNotSeeTableRecords([$other]);

    Livewire::test(ListUsers::class)
        ->searchTable('MONA@example.com')
        ->assertCanSeeTableRecords([$mona])
        ->assertCanNotSeeTableRecords([$other]);
});

it('finds a user in the global search by a number typed with spaces, showing it masked', function (): void {
    actingAsAdmin();
    User::factory()->withPhone('01012345678')->create(['name' => 'منى عادل']);
    User::factory()->withPhone('01112345678')->create(['name' => 'سارة']);

    Livewire::test(GlobalSearch::class)
        ->set('search', '0101 234 5678')
        ->assertSee('منى عادل')
        ->assertSee('010*****678')
        ->assertDontSee('سارة')
        ->assertDontSee('01012345678');
});

it('shows an account\'s profile, sign-in providers, devices and content counts', function (): void {
    actingAsAdmin();
    $resident = User::factory()->withPhone('01012345678')->create(['name' => 'منى عادل']);
    DeviceToken::factory()->for($resident)->create(['app_version' => '1.4.2']);
    Property::factory()->count(2)->for($resident)->create();

    Livewire::test(ViewUser::class, ['record' => $resident->getRouteKey()])
        ->assertSee('منى عادل')
        ->assertSee('010*****678')
        ->assertDontSee('01012345678')
        ->assertSee('1.4.2')
        ->assertSchemaStateSet(['properties_count' => 2]);
});

it('suspends an account with a reason, signs it out everywhere, and logs who and why', function (): void {
    $admin = actingAsAdmin(AdminRole::Moderator);
    $resident = User::factory()->create();
    $resident->createToken('Pixel 8');
    DB::table('sessions')->insert(['id' => 'session-1', 'user_id' => $resident->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    Livewire::test(ViewUser::class, ['record' => $resident->getRouteKey()])
        ->callAction('suspend', ['reason' => 'إعلانات وهمية متكررة'])
        ->assertHasNoActionErrors();

    $activity = Activity::query()->where('event', 'suspended')->sole();

    expect($resident->fresh()?->status)->toBe(UserStatus::Suspended)
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(DB::table('sessions')->count())->toBe(0)
        ->and($activity->log_name)->toBe(AdminLog::NAME)
        ->and($activity->causer?->is($admin))->toBeTrue()
        ->and($activity->subject?->is($resident))->toBeTrue()
        ->and($activity->properties?->get('reason'))->toBe('إعلانات وهمية متكررة')
        ->and($activity->attribute_changes?->toArray())->toBe(['old' => ['status' => 'active'], 'attributes' => ['status' => 'suspended']]);
});

it('requires a reason to suspend', function (): void {
    actingAsAdmin();
    $resident = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $resident->getRouteKey()])
        ->callAction('suspend', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($resident->fresh()?->status)->toBe(UserStatus::Active);
});

it('reactivates a suspended account and logs it', function (): void {
    actingAsAdmin(AdminRole::Moderator);
    $resident = User::factory()->suspended()->create();

    Livewire::test(ViewUser::class, ['record' => $resident->getRouteKey()])
        ->assertActionHidden('suspend')
        ->callAction('reactivate', ['reason' => 'راجعنا الإعلانات'])
        ->assertHasNoActionErrors();

    expect($resident->fresh())->status->toBe(UserStatus::Active)->suspended_at->toBeNull()
        ->and(Activity::query()->where('event', 'reactivated')->sole()->properties?->get('reason'))->toBe('راجعنا الإعلانات');
});

it('offers each action to the roles allowed it', function (AdminRole $role, array $visible, array $hidden): void {
    actingAsAdmin($role);
    $resident = User::factory()->withPhone()->create();
    $page = Livewire::test(ViewUser::class, ['record' => $resident->getRouteKey()]);

    foreach ($visible as $action) {
        $page->assertActionVisible($action);
    }

    foreach ($hidden as $action) {
        $page->assertActionHidden($action);
    }
})->with([
    'super_admin' => [AdminRole::SuperAdmin, ['suspend', 'updateRoles', 'revealPhone'], []],
    'moderator' => [AdminRole::Moderator, ['suspend', 'revealPhone'], ['updateRoles']],
    'sales' => [AdminRole::Sales, [], ['suspend', 'updateRoles', 'revealPhone']],
]);

it('never lets an admin suspend their own account', function (): void {
    $admin = actingAsAdmin();

    Livewire::test(ViewUser::class, ['record' => $admin->getRouteKey()])->assertActionHidden('suspend');
});

it('shows the full number once asked, and logs that it was shown without the number', function (): void {
    $admin = actingAsAdmin(AdminRole::Moderator);
    $resident = User::factory()->withPhone('01012345678')->create();

    Livewire::test(ViewUser::class, ['record' => $resident->getRouteKey()])
        ->callAction('revealPhone')
        ->assertNotified((string) __('admin.users.actions.revealed_phone', ['phone' => '01012345678']));

    $activity = Activity::query()->where('event', 'phone_revealed')->sole();

    expect($activity->causer?->is($admin))->toBeTrue()
        ->and(json_encode($activity->toArray()))->not->toContain('1012345678');
});

it('changes roles, for super admins only, and logs before and after', function (): void {
    actingAsAdmin();
    $resident = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $resident->getRouteKey()])
        ->callAction('updateRoles', ['roles' => ['moderator', 'sales'], 'reason' => 'انضم لفريق المراجعة'])
        ->assertHasNoActionErrors();

    expect($resident->fresh()?->getRoleNames()->sort()->values()->all())->toBe(['moderator', 'sales'])
        ->and(Activity::query()->where('event', 'roles_changed')->sole()->attribute_changes?->toArray())
        ->toBe(['old' => ['roles' => []], 'attributes' => ['roles' => ['moderator', 'sales']]]);
});

it('never takes the role from the last super admin', function (): void {
    $admin = actingAsAdmin();

    Livewire::test(ViewUser::class, ['record' => $admin->getRouteKey()])
        ->callAction('updateRoles', ['roles' => ['moderator']])
        ->assertNotified((string) __('errors.last_super_admin'));

    expect($admin->fresh()?->hasRole('super_admin'))->toBeTrue();

    // With a second one, it can go.
    User::factory()->admin()->create();

    Livewire::test(ViewUser::class, ['record' => $admin->getRouteKey()])
        ->callAction('updateRoles', ['roles' => ['moderator']]);

    expect($admin->fresh()?->getRoleNames()->all())->toBe(['moderator']);
});
