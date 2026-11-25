<?php

declare(strict_types=1);

use App\Domains\Identity\Models\SocialAccount;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\IdTokens;

beforeEach(function (): void {
    Date::setTestNow('2026-11-25 18:00:00');
    $this->tokens = new IdTokens()->publish();
});

function signInWithGoogle(string $idToken, string $device = 'Pixel 8'): TestResponse
{
    return test()->postJson('/api/v1/auth/google', ['id_token' => $idToken, 'device_name' => $device]);
}

it('creates an account on the first Google sign-in and answers 201 with a token', function (): void {
    $response = signInWithGoogle($this->tokens->google())->assertCreated();

    $user = User::query()->sole();

    expect($response->json('data.token'))->toBeString()->toStartWith(PersonalAccessToken::query()->sole()->getKey().'|')
        ->and($response->json('data.needs_phone'))->toBeTrue()
        ->and($response->json('data.user'))->toMatchArray([
            'id' => $user->id,
            'name' => 'أحمد السيد',
            'email' => 'ahmed.elsayed@gmail.com',
            'phone' => null,
            'needs_phone' => true,
            'avatar_url' => 'https://lh3.googleusercontent.com/a/ahmed',
            'locale' => 'ar',
        ])
        ->and($user->email_verified_at?->toIso8601ZuluString())->toBe('2026-11-25T18:00:00Z')
        ->and($user->socialAccounts()->sole())->toMatchArray([
            'provider' => 'google',
            'subject' => '110248495921238986420',
            'email' => 'ahmed.elsayed@gmail.com',
        ]);
});

it('finds the same account on the next sign-in and answers 200', function (): void {
    signInWithGoogle($this->tokens->google())->assertCreated();

    signInWithGoogle($this->tokens->google(), 'iPad')->assertOk()->assertJsonPath('data.user.email', 'ahmed.elsayed@gmail.com');

    expect(User::query()->count())->toBe(1)
        ->and(PersonalAccessToken::query()->pluck('name')->sort()->values()->all())->toBe(['Pixel 8', 'iPad']);
});

it('finds the account by provider and subject even when the email changed', function (): void {
    signInWithGoogle($this->tokens->google())->assertCreated();

    signInWithGoogle($this->tokens->google(['email' => 'ahmed.new@gmail.com']))->assertOk();

    expect(User::query()->sole()->email)->toBe('ahmed.elsayed@gmail.com');
});

it('replaces a device\'s token when it signs in again under the same name', function (): void {
    $first = signInWithGoogle($this->tokens->google())->json('data.token');
    $second = signInWithGoogle($this->tokens->google())->json('data.token');

    expect(PersonalAccessToken::query()->count())->toBe(1)
        ->and($second)->not->toBe($first);

    $this->withToken($first)->getJson('/api/v1/me')->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    $this->withToken($second)->getJson('/api/v1/me')->assertOk();
});

it('links Apple to an active account with the same verified email', function (): void {
    $user = User::factory()->create(['email' => 'x7k2p9@privaterelay.appleid.com']);
    SocialAccount::factory()->for($user)->create();

    $this->postJson('/api/v1/auth/apple', [
        'id_token' => $this->tokens->apple('nonce-1'),
        'nonce' => 'nonce-1',
        'device_name' => 'iPhone',
    ])->assertOk()->assertJsonPath('data.user.id', $user->id);

    expect($user->socialAccounts()->pluck('provider')->map->value->sort()->values()->all())->toBe(['apple', 'google']);
});

it('matches the email whatever its case', function (): void {
    $user = User::factory()->create(['email' => 'ahmed.elsayed@gmail.com']);

    signInWithGoogle($this->tokens->google(['email' => 'Ahmed.ElSayed@Gmail.com']))->assertOk()->assertJsonPath('data.user.id', $user->id);
});

it('never links an unverified email, and leaves it off the new account', function (): void {
    $owner = User::factory()->create(['email' => 'ahmed.elsayed@gmail.com']);

    signInWithGoogle($this->tokens->google(['email_verified' => false]))->assertCreated();

    $newUser = User::query()->whereKeyNot($owner->id)->sole();

    expect($newUser->email)->toBeNull()
        ->and($owner->socialAccounts()->count())->toBe(0);
});

it('creates a new account, without the email, when the account with it already has this provider', function (): void {
    $owner = User::factory()->create(['email' => 'ahmed.elsayed@gmail.com']);
    SocialAccount::factory()->for($owner)->create(['subject' => 'another-google-subject']);

    signInWithGoogle($this->tokens->google())->assertCreated()->assertJsonPath('data.user.email', null);

    expect(User::query()->count())->toBe(2);
});

it('uses the name the app sends for Apple, which puts none in its token', function (): void {
    $this->postJson('/api/v1/auth/apple', [
        'id_token' => $this->tokens->apple('nonce-1'),
        'nonce' => 'nonce-1',
        'name' => 'منى عبد الرحمن',
        'device_name' => 'iPhone',
    ])->assertCreated()->assertJsonPath('data.user.name', 'منى عبد الرحمن');
});

it('names an account the provider gave no name for', function (): void {
    $this->postJson('/api/v1/auth/apple', [
        'id_token' => $this->tokens->apple('nonce-1'),
        'nonce' => 'nonce-1',
        'device_name' => 'iPhone',
    ])->assertCreated()->assertJsonPath('data.user.name', 'مستخدم أوز إيه');
});

it('refuses an invalid token with one error that names no reason', function (): void {
    signInWithGoogle($this->tokens->forged())
        ->assertUnauthorized()
        ->assertExactJson(['message' => __('errors.invalid_identity_token', [], 'ar'), 'code' => 'invalid_identity_token', 'errors' => []]);

    expect(User::query()->count())->toBe(0);
});

it('refuses Apple without the nonce', function (): void {
    $this->postJson('/api/v1/auth/apple', ['id_token' => $this->tokens->apple(), 'device_name' => 'iPhone'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.nonce.0', 'حقل رمز التحقق مطلوب.');
});

it('names the missing fields in Arabic', function (): void {
    $this->postJson('/api/v1/auth/google', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.id_token.0', 'حقل رمز تسجيل الدخول مطلوب.')
        ->assertJsonPath('errors.device_name.0', 'حقل اسم الجهاز مطلوب.');
});

it('refuses a suspended account, found by its link or by its email', function (string $link): void {
    $user = User::factory()->suspended()->create(['email' => 'ahmed.elsayed@gmail.com']);

    if ($link === 'by its link') {
        SocialAccount::factory()->for($user)->create(['subject' => '110248495921238986420']);
    }

    signInWithGoogle($this->tokens->google())->assertForbidden()->assertJsonPath('code', 'account_suspended');

    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);
})->with(['by its link', 'by its email']);

it('signs in with the fake driver when it is on', function (): void {
    config(['awez.sign_in.fake' => true]);

    signInWithGoogle('fake:mona@example.com')->assertCreated()->assertJsonPath('data.user.email', 'mona@example.com');
    signInWithGoogle('fake:mona@example.com')->assertOk();
});

it('refuses the fake token while the fake driver is off', function (): void {
    signInWithGoogle('fake:mona@example.com')->assertUnauthorized()->assertJsonPath('code', 'invalid_identity_token');
});
