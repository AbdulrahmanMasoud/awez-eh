<?php

declare(strict_types=1);

use App\Domains\Geography\Models\City;
use App\Domains\Identity\Actions\SuspendUser;
use App\Domains\Identity\Actions\SyncAdminRoles;
use App\Domains\Identity\Actions\UpdateUserRoles;
use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Services\Models\Trade;
use App\Filament\Pages\ActivityLog;
use App\Filament\Resources\Trades\Pages\EditTrade;
use Database\Seeders\ReferenceDataSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->withoutVite();
});

it('opens the activity log for super admins only', function (AdminRole $role, int $status): void {
    actingAsAdmin($role);

    $this->get(ActivityLog::getUrl())->assertStatus($status);
})->with([
    [AdminRole::SuperAdmin, 200],
    [AdminRole::Moderator, 403],
    [AdminRole::DataEntry, 403],
    [AdminRole::Sales, 403],
]);

it('logs who changed reference data in the panel, and what', function (): void {
    $admin = actingAsAdmin(AdminRole::DataEntry);
    $trade = Trade::factory()->create(['name_ar' => 'سباك']);

    Livewire::test(EditTrade::class, ['record' => $trade->getRouteKey()])
        ->fillForm(['name_ar' => 'سباكة'])
        ->call('save')
        ->assertHasNoFormErrors();

    $activity = Activity::query()->where('event', 'updated')->sole();

    expect($activity->causer?->is($admin))->toBeTrue()
        ->and($activity->subject_type)->toBe('trade')
        ->and($activity->attribute_changes?->toArray())->toEqual(['old' => ['name_ar' => 'سباك'], 'attributes' => ['name_ar' => 'سباكة']]);
});

it('does not log changes made outside the panel: seeders, the API, jobs', function (): void {
    new ReferenceDataSeeder()->run(app(SyncAdminRoles::class));
    City::factory()->create();

    expect(Activity::query()->count())->toBe(0);
});

it('lists entries newest first, filtered by admin, action and date', function (): void {
    $admin = actingAsAdmin();
    $other = User::factory()->admin(AdminRole::Moderator)->create();
    $resident = User::factory()->create();
    AdminLog::record('suspended', $resident, $admin, 'سبام');
    AdminLog::record('phone_revealed', $resident, $other);

    $entries = Activity::query()->where('log_name', 'admin')->get();
    $suspended = $entries->firstWhere('event', 'suspended');
    $revealed = $entries->firstWhere('event', 'phone_revealed');

    Livewire::test(ActivityLog::class)
        ->assertCanSeeTableRecords([$revealed, $suspended], inOrder: true)
        ->assertSee('سبام')
        ->filterTable('event', 'suspended')
        ->assertCanSeeTableRecords([$suspended])
        ->assertCanNotSeeTableRecords([$revealed]);

    Livewire::test(ActivityLog::class)
        ->filterTable('causer_id', $other->id)
        ->assertCanSeeTableRecords([$revealed])
        ->assertCanNotSeeTableRecords([$suspended]);

    Livewire::test(ActivityLog::class)
        ->filterTable('created_at', ['from' => now()->addDay()->toDateString()])
        ->assertCanNotSeeTableRecords([$revealed, $suspended]);
});

it('never logs personal data for users: statuses and roles only', function (): void {
    $admin = actingAsAdmin();
    $resident = User::factory()->withPhone('01012345678')->create(['name' => 'منى عادل', 'email' => 'mona@example.com']);

    app(SuspendUser::class)->handle($resident, $admin, 'سبام');
    app(UpdateUserRoles::class)->handle($resident, [AdminRole::Sales], $admin);

    $logged = json_encode(Activity::query()->get()->toArray());

    expect($logged)->not->toContain('منى')->not->toContain('mona@')->not->toContain('1012345678');
});
