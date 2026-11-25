<?php

declare(strict_types=1);

use App\Domains\Identity\Models\DeviceToken;
use App\Domains\Identity\Models\PhoneVerification;
use App\Domains\Identity\Models\SocialAccount;
use App\Domains\Identity\Models\User;
use Database\Factories\PhoneVerificationFactory;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// Invariant 1 for the account endpoints: none takes a user's id, so each answers for and changes
// only the account of the token it is sent with.

beforeEach(function (): void {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
    $this->aliceToken = $this->alice->createToken('Pixel 8')->plainTextToken;
    $this->bobToken = $this->bob->createToken('iPhone')->plainTextToken;
});

function asAccountOf(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

it('takes no user id on any account route', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/'));

    expect($routes)->not->toBeEmpty()
        ->and($routes->filter(fn (RoutingRoute $route): bool => $route->parameterNames() !== [])->map->uri()->values()->all())->toBe([]);
});

it('answers me for the token\'s user whatever else the request names', function (): void {
    asAccountOf($this->aliceToken)->getJson('/api/v1/me?user_id='.$this->bob->id)->assertJsonPath('data.id', $this->alice->id);
    asAccountOf($this->bobToken)->getJson('/api/v1/me')->assertJsonPath('data.id', $this->bob->id);
});

it('changes only the token\'s own profile', function (): void {
    asAccountOf($this->aliceToken)->patchJson('/api/v1/me', ['name' => 'Mallory', 'id' => $this->bob->id, 'user_id' => $this->bob->id])
        ->assertOk()
        ->assertJsonPath('data.id', $this->alice->id);

    expect($this->bob->fresh()?->name)->toBe('Bob')
        ->and($this->alice->fresh()?->name)->toBe('Mallory');
});

it('cannot confirm a number with another user\'s confirmation', function (): void {
    PhoneVerification::factory()->for($this->bob)->create();

    asAccountOf($this->aliceToken)->postJson('/api/v1/me/phone/confirm', ['code' => PhoneVerificationFactory::CODE])
        ->assertJsonPath('code', 'verification_code_expired');

    expect($this->alice->fresh()?->phone)->toBeNull()
        ->and($this->bob->fresh()?->phone)->toBeNull()
        ->and(PhoneVerification::query()->sole()->attempts)->toBe(0);
});

it('cannot touch another user\'s device token', function (): void {
    asAccountOf($this->bobToken)->putJson('/api/v1/me/device-token', ['token' => 'bob-fcm', 'platform' => 'ios'])->assertSuccessful();

    asAccountOf($this->aliceToken)->putJson('/api/v1/me/device-token', ['token' => 'alice-fcm', 'platform' => 'android', 'user_id' => $this->bob->id])->assertSuccessful();
    asAccountOf($this->aliceToken)->postJson('/api/v1/auth/sign-out')->assertNoContent();

    expect(DeviceToken::query()->sole())->token->toBe('bob-fcm')->user_id->toBe($this->bob->id);
});

it('cannot delete another user\'s account', function (): void {
    asAccountOf($this->aliceToken)->deleteJson('/api/v1/me', ['user_id' => $this->bob->id])->assertNoContent();

    expect($this->bob->fresh()?->name)->toBe('Bob')
        ->and($this->bob->tokens()->count())->toBe(1);
    asAccountOf($this->bobToken)->getJson('/api/v1/me')->assertOk();
});

it('lets nobody reach an Identity row by id, not even its owner', function (string $model): void {
    $row = $model::factory()->create(['user_id' => $this->alice->id]);

    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($this->alice)->allows($ability, $row))->toBeFalse()
            ->and(Gate::forUser($this->bob)->allows($ability, $row))->toBeFalse();
    }
})->with([DeviceToken::class, PhoneVerification::class, SocialAccount::class]);
