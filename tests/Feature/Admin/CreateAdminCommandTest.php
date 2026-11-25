<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * @return array<string, string>
 */
function createAdminOptions(string $email = 'owner@awez.test', string $role = 'super_admin'): array
{
    return ['--name' => 'عبدالرحمن', '--email' => $email, '--role' => $role];
}

it('creates the first super admin, and prints nothing secret', function (): void {
    $this->artisan('awez:create-admin', createAdminOptions('Owner@Awez.test'))
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-secret-passphrase')
        ->expectsQuestion('Password again', 'a-long-secret-passphrase')
        ->expectsOutputToContain('Created owner@awez.test as super_admin')
        ->doesntExpectOutputToContain('a-long-secret-passphrase')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'owner@awez.test')->sole();

    expect($admin->hasRole(AdminRole::SuperAdmin->value))->toBeTrue()
        ->and(Hash::check('a-long-secret-passphrase', (string) $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull();
});

it('promotes an existing account, keeping its name', function (): void {
    $resident = User::factory()->create(['name' => 'منى عادل', 'email' => 'mona@example.com']);

    $this->artisan('awez:create-admin', createAdminOptions('mona@example.com', 'moderator'))
        ->expectsQuestion('Password (at least 12 characters)', 'another-long-secret')
        ->expectsQuestion('Password again', 'another-long-secret')
        ->expectsOutputToContain('Promoted mona@example.com as moderator')
        ->assertSuccessful();

    expect($resident->fresh())->name->toBe('منى عادل')
        ->and($resident->fresh()?->hasRole('moderator'))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});

it('refuses a short password, mismatched ones and an unknown role', function (string $password, string $again, string $role): void {
    $this->artisan('awez:create-admin', createAdminOptions(role: $role))
        ->expectsQuestion('Password (at least 12 characters)', $password)
        ->expectsQuestion('Password again', $again)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'too short' => ['short-pass', 'short-pass', 'super_admin'],
    'not confirmed' => ['a-long-secret-passphrase', 'another-long-passphrase', 'super_admin'],
    'unknown role' => ['a-long-secret-passphrase', 'a-long-secret-passphrase', 'owner'],
]);

it('refuses to make a suspended account an admin', function (): void {
    User::factory()->suspended()->create(['email' => 'mona@example.com']);

    $this->artisan('awez:create-admin', createAdminOptions('mona@example.com'))
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-secret-passphrase')
        ->expectsQuestion('Password again', 'a-long-secret-passphrase')
        ->assertFailed();

    expect(User::query()->sole()->hasRole('super_admin'))->toBeFalse();
});

it('refuses to run without a terminal, since the password is only ever typed', function (): void {
    $this->artisan('awez:create-admin', [...createAdminOptions(), '--no-interaction' => true])
        ->expectsOutputToContain('Run this in a terminal')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
