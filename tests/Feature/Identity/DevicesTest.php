<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\DevicePlatform;
use App\Domains\Identity\Models\DeviceToken;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

beforeEach(function (): void {
    Date::setTestNow('2026-11-25 18:00:00');
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('Pixel 8')->plainTextToken;
});

/**
 * Requests with a real access token, as the app sends them: device tokens hang off it.
 */
function asDevice(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

it('saves the push token of the device behind the access token', function (): void {
    asDevice($this->token)->putJson('/api/v1/me/device-token', ['token' => 'fcm-token-1', 'platform' => 'android', 'app_version' => '1.0.0'])
        ->assertCreated()
        ->assertExactJson(['data' => ['platform' => 'android', 'app_version' => '1.0.0', 'last_used_at' => '2026-11-25T18:00:00Z']]);

    $device = DeviceToken::query()->sole();

    expect($device->user_id)->toBe($this->user->id)
        ->and($device->personal_access_token_id)->toBe(PersonalAccessToken::query()->sole()->getKey())
        ->and($device->token)->toBe('fcm-token-1')
        ->and($device->platform)->toBe(DevicePlatform::Android);
});

it('replaces the device\'s push token, one per access token', function (): void {
    asDevice($this->token)->putJson('/api/v1/me/device-token', ['token' => 'fcm-token-1', 'platform' => 'android'])->assertCreated();
    Date::setTestNow('2026-11-26 09:00:00');
    asDevice($this->token)->putJson('/api/v1/me/device-token', ['token' => 'fcm-token-2', 'platform' => 'android', 'app_version' => '1.1.0'])->assertOk();

    expect(DeviceToken::query()->sole())->token->toBe('fcm-token-2')->app_version->toBe('1.1.0')
        ->and(DeviceToken::query()->sole()->last_used_at->toIso8601ZuluString())->toBe('2026-11-26T09:00:00Z');
});

it('keeps one push token per device across accounts', function (): void {
    asDevice($this->token)->putJson('/api/v1/me/device-token', ['token' => 'fcm-shared-phone', 'platform' => 'ios'])->assertSuccessful();

    // The phone changes hands: someone else signs in on it.
    $other = User::factory()->create();
    asDevice($other->createToken('iPhone')->plainTextToken)->putJson('/api/v1/me/device-token', ['token' => 'fcm-shared-phone', 'platform' => 'ios'])->assertSuccessful();

    expect(DeviceToken::query()->sole()->user_id)->toBe($other->id);
});

it('refuses a platform it does not know', function (): void {
    asDevice($this->token)->putJson('/api/v1/me/device-token', ['token' => 'fcm-token-1', 'platform' => 'windows'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.platform.0', 'قيمة حقل نظام التشغيل غير موجودة في قائمة القيم المسموح بها.');
});

it('signs out of this device only, with its push token', function (): void {
    $tablet = $this->user->createToken('iPad')->plainTextToken;
    asDevice($this->token)->putJson('/api/v1/me/device-token', ['token' => 'fcm-token-1', 'platform' => 'android'])->assertSuccessful();

    asDevice($this->token)->postJson('/api/v1/auth/sign-out')->assertNoContent();

    asDevice($this->token)->getJson('/api/v1/me')->assertUnauthorized();
    asDevice($tablet)->getJson('/api/v1/me')->assertOk();

    expect(DeviceToken::query()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->sole()->name)->toBe('iPad');
});
