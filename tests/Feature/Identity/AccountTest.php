<?php

declare(strict_types=1);

use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Places\Models\Place;
use App\Domains\Services\Models\Provider;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;

it('answers GET me with the token\'s own account', function (): void {
    Date::setTestNow('2026-11-25 14:05:09.123456');
    $user = User::factory()->withPhone('01012345678')->create(['name' => 'أحمد السيد', 'email' => 'ahmed@example.com']);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => 'أحمد السيد',
            'email' => 'ahmed@example.com',
            // Their own number, in full: it is theirs.
            'phone' => '+201012345678',
            'phone_verified_at' => '2026-11-25T14:05:09Z',
            'needs_phone' => false,
            'avatar_url' => null,
            'locale' => 'ar',
            'district_id' => null,
            'is_advertiser' => false,
            'is_provider' => false,
            'created_at' => '2026-11-25T14:05:09Z',
        ]]);
});

it('says the account needs a phone until one is confirmed', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me')->assertJsonPath('data.needs_phone', true)->assertJsonPath('data.phone', null);
});

it('derives advertiser and provider from what the account owns', function (): void {
    $user = User::factory()->create();
    Place::factory()->for($user)->create();
    Provider::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')->assertJsonPath('data.is_advertiser', true)->assertJsonPath('data.is_provider', true);
});

it('names its routes under api.v1', function (): void {
    expect(route('api.v1.me.show', absolute: false))->toBe('/api/v1/me')
        ->and(route('api.v1.auth.google', absolute: false))->toBe('/api/v1/auth/google');
});

it('answers in JSON whatever the client accepts', function (string $accept): void {
    Sanctum::actingAs(User::factory()->create());

    $this->get('/api/v1/me', ['Accept' => $accept])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['data' => ['id']]);
})->with(['text/html', '*/*', 'application/xml']);

it('refuses a request without a token', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('refuses a suspended account on every signed-in route', function (string $method, string $uri): void {
    Sanctum::actingAs(User::factory()->suspended()->create());

    $this->json($method, $uri)->assertForbidden()->assertJsonPath('code', 'account_suspended');
})->with([
    ['GET', '/api/v1/me'],
    ['PATCH', '/api/v1/me'],
    ['DELETE', '/api/v1/me'],
    ['POST', '/api/v1/me/phone'],
    ['POST', '/api/v1/me/phone/confirm'],
    ['PUT', '/api/v1/me/device-token'],
    ['POST', '/api/v1/auth/sign-out'],
]);

it('writes last_seen_at at most once every five minutes', function (): void {
    Date::setTestNow('2026-11-25 18:00:00');
    $user = User::factory()->create();
    $updatedAt = $user->updated_at?->toIso8601ZuluString();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')->assertOk();
    expect($user->fresh()?->last_seen_at?->toIso8601ZuluString())->toBe('2026-11-25T18:00:00Z');

    Date::setTestNow('2026-11-25 18:04:59');
    $this->getJson('/api/v1/me')->assertOk();
    expect($user->fresh()?->last_seen_at?->toIso8601ZuluString())->toBe('2026-11-25T18:00:00Z');

    Date::setTestNow('2026-11-25 18:05:01');
    $this->getJson('/api/v1/me')->assertOk();
    expect($user->fresh()?->last_seen_at?->toIso8601ZuluString())->toBe('2026-11-25T18:05:01Z')
        // Being seen is not a change to the profile.
        ->and($user->fresh()?->updated_at?->toIso8601ZuluString())->toBe($updatedAt);
});

it('changes only the profile fields sent', function (): void {
    $district = District::factory()->create();
    $user = User::factory()->create(['name' => 'أحمد']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['locale' => 'en'])->assertOk()->assertJsonPath('data.locale', 'en')->assertJsonPath('data.name', 'أحمد');
    $this->patchJson('/api/v1/me', ['name' => '  أحمد السيد ', 'district_id' => $district->id])
        ->assertOk()
        ->assertJsonPath('data.name', 'أحمد السيد')
        ->assertJsonPath('data.district_id', $district->id)
        ->assertJsonPath('data.locale', 'en');
    $this->patchJson('/api/v1/me', ['district_id' => null])->assertOk()->assertJsonPath('data.district_id', null);
});

it('refuses profile values that are not allowed', function (array $body, string $field): void {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me', $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['locale' => 'fr'], 'locale'],
    [['name' => ''], 'name'],
    [['district_id' => '0196b1d2-5c4e-7a3b-9f1d-2e3c4b5a6d7e'], 'district_id'],
    [['district_id' => 'seven'], 'district_id'],
]);

it('limits guests per IP address', function (): void {
    config(['awez.rate_limits.api.per_ip_per_minute' => 2]);

    // A public route: on signed-in ones, auth:sanctum answers a guest before the limiter counts.
    $this->postJson('/api/v1/auth/google')->assertUnprocessable();
    $this->postJson('/api/v1/auth/google')->assertUnprocessable();
    $this->postJson('/api/v1/auth/google')
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson('/api/v1/auth/google')->assertUnprocessable();
});

it('limits signed-in users per user, not per IP address', function (): void {
    config(['awez.rate_limits.api.per_user_per_minute' => 1, 'awez.rate_limits.api.per_ip_per_minute' => 100]);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/me')->assertTooManyRequests();

    // Same address, another resident.
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/me')->assertOk();
});
