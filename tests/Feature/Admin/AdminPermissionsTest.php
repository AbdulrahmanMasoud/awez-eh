<?php

declare(strict_types=1);

use App\Domains\Geography\Models\City;
use App\Domains\Identity\Actions\CreateAdmin;
use App\Domains\Identity\Actions\SyncAdminRoles;
use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminPermissions;
use App\Support\AdminArea;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Database\Seeders\DemoAdminSeeder;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('names exactly the permissions Shield shows on the roles screen', function (): void {
    Filament::setCurrentPanel('admin');

    $shield = FilamentShield::getEntitiesPermissions();
    $ours = AdminPermissions::all();
    sort($shield);
    sort($ours);

    expect($ours)->toBe($shield);
});

it('seeds the four roles with their permissions, again and again', function (): void {
    app(SyncAdminRoles::class)->handle();
    app(SyncAdminRoles::class)->handle();

    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe(['data_entry', 'moderator', 'sales', 'super_admin'])
        ->and(Permission::query()->count())->toBe(count(AdminPermissions::all()))
        ->and(Role::findByName('super_admin')->permissions()->count())->toBe(count(AdminPermissions::all()))
        ->and(Role::findByName('moderator')->permissions()->pluck('name')->sort()->values()->all())
        ->toBe(['Reactivate:User', 'RevealPhone:User', 'Suspend:User', 'View:User', 'ViewAny:User'])
        ->and(Role::findByName('sales')->permissions()->pluck('name')->sort()->values()->all())->toBe(['View:User', 'ViewAny:User']);
});

it('keeps what a super admin added to a role when the roles are seeded again', function (): void {
    app(SyncAdminRoles::class)->handle();
    Role::findByName('sales')->givePermissionTo('ViewAny:City');

    app(SyncAdminRoles::class)->handle();

    expect(Role::findByName('sales')->hasPermissionTo('ViewAny:City'))->toBeTrue();
});

it('grants admin permissions inside the panel only: through the API an admin is a resident', function (): void {
    $admin = User::factory()->admin(AdminRole::DataEntry)->create();
    $city = City::factory()->create();

    Filament::setCurrentPanel(null);
    expect(AdminArea::isActive())->toBeFalse()
        ->and($admin->can('update', $city))->toBeFalse()
        ->and($admin->mayAdminister('Update:City'))->toBeFalse();

    Filament::setCurrentPanel('admin');
    expect($admin->can('update', $city))->toBeTrue();
});

it('gives a resident no admin permission anywhere', function (): void {
    $resident = User::factory()->create();
    Filament::setCurrentPanel('admin');

    expect($resident->isAdmin())->toBeFalse()
        ->and($resident->can('viewAny', City::class))->toBeFalse()
        ->and($resident->can('viewAny', User::class))->toBeFalse();
});

it('seeds the demo admin in local and testing only', function (): void {
    app(DemoAdminSeeder::class)->run(app(CreateAdmin::class));

    expect(User::query()->where('email', DemoAdminSeeder::EMAIL)->sole()->hasRole('super_admin'))->toBeTrue();

    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(DemoAdminSeeder::class)->run(app(CreateAdmin::class)))->toThrow(LogicException::class);

    app()->detectEnvironment(fn (): string => 'testing');
});
