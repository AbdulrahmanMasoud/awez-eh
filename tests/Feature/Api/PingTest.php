<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;

it('answers the ping with a small resource', function (): void {
    Date::setTestNow('2026-11-24 14:05:09.123456');

    $this->getJson('/api/v1/ping')
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok', 'locale' => 'ar', 'time' => '2026-11-24T14:05:09Z']]);
});

it('names its routes under api.v1', function (): void {
    expect(route('api.v1.ping', absolute: false))->toBe('/api/v1/ping');
});

it('answers in JSON whatever the client accepts', function (string $accept): void {
    $this->get('/api/v1/ping', ['Accept' => $accept])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('data.status', 'ok');
})->with(['text/html', '*/*', 'application/xml']);

it('limits guests per IP address', function (): void {
    config(['awez.rate_limits.api.per_ip_per_minute' => 2]);

    $this->getJson('/api/v1/ping')->assertOk();
    $this->getJson('/api/v1/ping')->assertOk();
    $this->getJson('/api/v1/ping')
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->getJson('/api/v1/ping')->assertOk();
});

it('limits signed-in users per user, not per IP address', function (): void {
    config(['awez.rate_limits.api.per_user_per_minute' => 1, 'awez.rate_limits.api.per_ip_per_minute' => 100]);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/ping')->assertOk();
    $this->getJson('/api/v1/ping')->assertTooManyRequests();

    // Same address, another resident.
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/ping')->assertOk();
});
