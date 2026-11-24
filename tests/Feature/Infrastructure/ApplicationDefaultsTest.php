<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;

/**
 * Boots the application's defaults again as the given environment would.
 */
function bootDefaultsAs(string $environment): void
{
    app()->detectEnvironment(fn (): string => $environment);
    app()->getProvider(AppServiceProvider::class)?->boot();
}

// The defaults are static: put the testing ones back so no other test runs as production.
afterEach(fn () => bootDefaultsAs('testing'));

it('makes models strict outside production', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

it('hands out immutable dates', function (): void {
    expect(now())->toBeInstanceOf(CarbonImmutable::class)
        ->and(Date::now())->toBeInstanceOf(CarbonImmutable::class)
        ->and(Date::parse('2026-11-24 10:00'))->toBeInstanceOf(CarbonImmutable::class);
});

it('keeps time in UTC and Cairo time for presentation only', function (): void {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(now()->getTimezone()->getName())->toBe('UTC')
        ->and(config('awez.display_timezone'))->toBe('Africa/Cairo');
});

it('relaxes strict models in production', function (): void {
    bootDefaultsAs('production');

    expect(Model::preventsLazyLoading())->toBeFalse();
});

it('forces HTTPS in production', function (): void {
    bootDefaultsAs('production');

    expect(URL::to('/listings'))->toStartWith('https://');
});

it('does not force HTTPS outside production', function (): void {
    expect(URL::to('/listings'))->toStartWith('http://');
});

it('refuses destructive database commands in production', function (string $command): void {
    bootDefaultsAs('production');

    // The connection does not exist, so even a command that slipped through could touch nothing.
    $this->artisan($command, ['--database' => 'prohibited_probe', '--force' => true])
        ->expectsOutputToContain('prohibited')
        ->assertFailed();
})->with(['db:wipe', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback']);
