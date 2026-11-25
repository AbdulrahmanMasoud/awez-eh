<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use App\Http\Middleware\Admin\AuthenticateAdmin;
use App\Http\Middleware\Admin\SetAdminLocale;
use Database\Factories\UserFactory;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * The sidebar's group and item labels, in order.
 *
 * @return list<string>
 */
function sidebarLabels(string|false $html): array
{
    preg_match_all('/fi-sidebar-(?:item|group)-label[^>]*>\s*([^<]+?)\s*</u', (string) $html, $matches);

    return $matches[1];
}

it('sends a guest to sign in', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk()->assertSee('dir="rtl"', false);
});

it('refuses a resident, even one signed in', function (): void {
    $this->actingAs(User::factory()->withPhone()->create())->get('/admin')->assertForbidden();
});

it('refuses an admin role without a password, and a password without an admin role', function (User $user): void {
    $this->actingAs($user)->get('/admin')->assertForbidden();
})->with([
    'role, no password' => fn (): User => tap(User::factory()->admin()->create(), fn (User $user) => $user->forceFill(['password' => null])->save()),
    'password, no role' => fn (): User => User::factory()->create(['password' => UserFactory::ADMIN_PASSWORD]),
]);

it('sends an admin without two-factor authentication to set it up before anything else', function (): void {
    $admin = User::factory()->admin()->withoutTwoFactor()->create();

    $this->actingAs($admin)->get('/admin')->assertRedirectContains('/admin/multi-factor-authentication');
});

it('lets an admin with two-factor authentication in, in Arabic and right to left', function (): void {
    $this->actingAs(User::factory()->admin()->create(['name' => 'عبدالرحمن مسعود']))
        ->get('/admin')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('يا عبدالرحمن');
});

it('signs a suspended admin out on the next request', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin')->assertOk();

    $admin->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();

    $this->get('/admin')->assertRedirect('/admin/login');
    $this->assertGuest();
});

it('answers in English for an admin who chose it, the sidebar groups included', function (): void {
    $page = $this->actingAs(User::factory()->admin()->create(['locale' => 'en']))
        ->get('/admin')
        ->assertOk()
        ->assertSee('dir="ltr"', false);

    expect(sidebarLabels($page->getContent()))->toContain('Home', 'System', 'Users')->not->toContain('النظام');
});

it('shows each role exactly its navigation', function (AdminRole $role, array $sees, array $doesNotSee): void {
    $this->actingAs(User::factory()->admin($role)->create());

    $labels = sidebarLabels($this->get('/admin')->assertOk()->getContent());

    expect($labels)->toContain(...$sees);

    foreach ($doesNotSee as $label) {
        expect($labels)->not->toContain($label);
    }
})->with([
    'super_admin' => [AdminRole::SuperAdmin, ['المستخدمين', 'الأدوار', 'سجل النشاط', 'المدن', 'المناطق', 'الحرف', 'أقسام الأماكن', 'النظام'], []],
    'moderator' => [AdminRole::Moderator, ['المستخدمين'], ['الأدوار', 'سجل النشاط', 'المدن', 'الحرف', 'أقسام الأماكن']],
    'data_entry' => [AdminRole::DataEntry, ['المدن', 'المناطق', 'الحرف', 'أقسام الأماكن'], ['المستخدمين', 'الأدوار', 'سجل النشاط']],
    'sales' => [AdminRole::Sales, ['المستخدمين'], ['الأدوار', 'سجل النشاط', 'المدن', 'الحرف']],
]);

it('hides the groups that have nothing yet', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $labels = sidebarLabels($this->get('/admin')->assertOk()->getContent());

    expect($labels)->toContain('النظام')
        ->not->toContain('المحتوى')
        ->not->toContain('النشاط');
});

it('re-runs only the locale and the suspension check on Livewire requests, never the session or the cookies', function (): void {
    $persistent = Livewire::getPersistentMiddleware();

    expect($persistent)->toContain(SetAdminLocale::class, AuthenticateAdmin::class)
        ->not->toContain(StartSession::class)
        ->not->toContain(EncryptCookies::class)
        ->not->toContain(AddQueuedCookiesToResponse::class)
        ->not->toContain(PreventRequestForgery::class);
});
