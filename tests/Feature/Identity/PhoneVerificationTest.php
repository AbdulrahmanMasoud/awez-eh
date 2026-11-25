<?php

declare(strict_types=1);

use App\Domains\Identity\Data\PhoneChallenge;
use App\Domains\Identity\Enums\PhoneVerificationDriver;
use App\Domains\Identity\Models\PhoneVerification;
use App\Domains\Identity\Models\User;
use App\Http\Resources\V1\Identity\PhoneChallengeResource;
use App\Support\PhoneNumber;
use Database\Factories\PhoneVerificationFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Date::setTestNow('2026-11-25 18:00:00');

    // Each log of this test in its own files, so what reached which channel can be read back.
    $this->logs = storage_path('framework/testing/logs-'.Str::random(8));
    File::ensureDirectoryExists($this->logs);
    config([
        'logging.channels.phone_verification.path' => "{$this->logs}/phone-verification.log",
        'logging.channels.single.path' => "{$this->logs}/laravel.log",
        'logging.channels.json.path' => "{$this->logs}/laravel.json",
        'logging.channels.stack.channels' => ['single', 'json'],
        'logging.default' => 'stack',
    ]);

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

afterEach(function (): void {
    File::deleteDirectory($this->logs);
});

/**
 * The code the `log` driver wrote last.
 */
function codeFromLog(string $logs): string
{
    preg_match_all('/Phone verification code (\d{6}) for/', (string) file_get_contents("{$logs}/phone-verification.log"), $matches);

    return (string) end($matches[1]);
}

it('starts a confirmation that asks for a six-digit code', function (): void {
    $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])
        ->assertCreated()
        ->assertExactJson(['data' => ['method' => 'code', 'length' => 6, 'expires_at' => '2026-11-25T18:10:00Z']]);

    $verification = PhoneVerification::query()->sole();

    expect($verification->phone->e164())->toBe('+201012345678')
        ->and($verification->driver)->toBe(PhoneVerificationDriver::Log)
        ->and($verification->attempts)->toBe(0)
        ->and($verification->ip_address)->toBe('127.0.0.1')
        // Only the HMAC is stored.
        ->and($verification->code_hash)->not->toContain(codeFromLog($this->logs))
        ->and(strlen($verification->code_hash))->toBe(64);
});

it('writes the code to the phone_verification channel only, with the number masked', function (): void {
    Log::info('Something else happened.');
    $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])->assertCreated();

    $code = codeFromLog($this->logs);
    $verificationLog = (string) file_get_contents("{$this->logs}/phone-verification.log");

    expect($code)->toMatch('/\A\d{6}\z/')
        ->and($verificationLog)->toContain('for 010*****678')
        ->and($verificationLog)->not->toContain('01012345678')
        ->and($verificationLog)->not->toContain('+201012345678')
        ->and((string) file_get_contents("{$this->logs}/laravel.log"))->toContain('Something else happened.')->not->toContain($code)
        ->and((string) file_get_contents("{$this->logs}/laravel.json"))->not->toContain($code);
});

it('confirms the number with the right code', function (): void {
    $this->postJson('/api/v1/me/phone', ['phone' => '٠١٠١٢٣٤٥٦٧٨'])->assertCreated();

    $this->postJson('/api/v1/me/phone/confirm', ['code' => codeFromLog($this->logs)])
        ->assertOk()
        ->assertJsonPath('data.phone', '+201012345678')
        ->assertJsonPath('data.phone_verified_at', '2026-11-25T18:00:00Z')
        ->assertJsonPath('data.needs_phone', false);

    expect(PhoneVerification::query()->sole()->confirmed_at?->toIso8601ZuluString())->toBe('2026-11-25T18:00:00Z');
});

it('counts a wrong code and says how many attempts are left', function (): void {
    PhoneVerification::factory()->for($this->user)->create();

    $this->postJson('/api/v1/me/phone/confirm', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertExactJson(['message' => __('errors.verification_code_invalid', [], 'ar'), 'code' => 'verification_code_invalid', 'errors' => ['attempts_left' => 4]]);

    expect(PhoneVerification::query()->sole()->attempts)->toBe(1)
        ->and($this->user->fresh()?->phone)->toBeNull();
});

it('expires a confirmation once its five attempts are used, even with the right code', function (): void {
    PhoneVerification::factory()->for($this->user)->create();

    foreach ([4, 3, 2, 1, 0] as $left) {
        $this->postJson('/api/v1/me/phone/confirm', ['code' => '999999'])->assertJsonPath('errors.attempts_left', $left);
    }

    $this->postJson('/api/v1/me/phone/confirm', ['code' => PhoneVerificationFactory::CODE])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'verification_code_expired');
});

it('expires a confirmation after ten minutes', function (): void {
    PhoneVerification::factory()->for($this->user)->create();

    Date::setTestNow('2026-11-25 18:10:00');

    $this->postJson('/api/v1/me/phone/confirm', ['code' => PhoneVerificationFactory::CODE])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'verification_code_expired');
});

it('answers expired when there is nothing to confirm, or it was confirmed already', function (): void {
    $this->postJson('/api/v1/me/phone/confirm', ['code' => '123456'])->assertJsonPath('code', 'verification_code_expired');

    PhoneVerification::factory()->for($this->user)->create();
    $this->postJson('/api/v1/me/phone/confirm', ['code' => PhoneVerificationFactory::CODE])->assertOk();
    $this->postJson('/api/v1/me/phone/confirm', ['code' => PhoneVerificationFactory::CODE])->assertJsonPath('code', 'verification_code_expired');
});

it('replaces an open confirmation when a new one starts', function (): void {
    $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])->assertCreated();
    Date::setTestNow('2026-11-25 18:01:00');
    $this->postJson('/api/v1/me/phone', ['phone' => '01112345678'])->assertCreated();
    $secondCode = codeFromLog($this->logs);

    expect(PhoneVerification::query()->open()->sole()->phone->e164())->toBe('+201112345678');

    $this->postJson('/api/v1/me/phone/confirm', ['code' => $secondCode])->assertOk()->assertJsonPath('data.phone', '+201112345678');
});

it('refuses a number another account has confirmed, when starting and when confirming', function (): void {
    $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])->assertCreated();
    $code = codeFromLog($this->logs);

    User::factory()->withPhone('01012345678')->create();

    $this->postJson('/api/v1/me/phone/confirm', ['code' => $code])
        ->assertStatus(409)
        ->assertExactJson(['message' => __('errors.phone_taken', [], 'ar'), 'code' => 'phone_taken', 'errors' => []]);
    $this->postJson('/api/v1/me/phone', ['phone' => '+20 101 234 5678'])->assertStatus(409)->assertJsonPath('code', 'phone_taken');

    expect($this->user->fresh()?->phone)->toBeNull()
        ->and(PhoneVerification::query()->sole()->confirmed_at)->toBeNull();
});

it('lets an account confirm its own number again', function (): void {
    $this->user->forceFill(['phone' => PhoneNumber::from('01012345678'), 'phone_verified_at' => Date::now()])->save();

    $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])->assertCreated();
});

it('refuses what is not an Egyptian mobile number', function (string $phone): void {
    $this->postJson('/api/v1/me/phone', ['phone' => $phone])->assertUnprocessable()->assertJsonPath('code', 'invalid_phone_number');
})->with(['0482345678', '12345', '+44 7700 900123']);

it('names the missing fields in Arabic', function (): void {
    $this->postJson('/api/v1/me/phone', [])->assertJsonPath('errors.phone.0', 'حقل رقم الموبايل مطلوب.');
    $this->postJson('/api/v1/me/phone/confirm', ['code' => '12'])->assertJsonPath('errors.code.0', 'يجب أن يحتوي حقل كود التأكيد على 6 رقمًا/أرقام.');
});

it('allows three starts per number in ten minutes', function (): void {
    foreach (['18:00:00', '18:03:00', '18:06:00'] as $time) {
        Date::setTestNow("2026-11-25 {$time}");
        $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])->assertCreated();
    }

    Date::setTestNow('2026-11-25 18:09:00');
    $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_attempts')
        // The 18:00 start leaves the window at 18:10.
        ->assertHeader('Retry-After', '60');

    // Another number from the same account is not that number's limit.
    $this->postJson('/api/v1/me/phone', ['phone' => '01112345678'])->assertCreated();

    Date::setTestNow('2026-11-25 18:10:01');
    $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])->assertCreated();
});

it('counts a number\'s starts across accounts, and ten a day', function (): void {
    foreach (range(0, 9) as $hour) {
        PhoneVerification::factory()->create(['phone' => PhoneNumber::from('01012345678'), 'created_at' => Date::now()->subHours($hour)->subMinutes(30)]);
    }

    $this->postJson('/api/v1/me/phone', ['phone' => '01012345678'])->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
});

it('allows five starts per account an hour', function (): void {
    PhoneVerification::factory()->for($this->user)->count(5)->create(['created_at' => Date::now()->subMinutes(30)]);

    $this->postJson('/api/v1/me/phone', ['phone' => '01512345678'])
        ->assertStatus(429)
        ->assertHeader('Retry-After', (string) (30 * 60));
});

it('allows twenty starts per address an hour', function (): void {
    PhoneVerification::factory()->count(20)->create(['ip_address' => '127.0.0.1', 'created_at' => Date::now()->subMinutes(10)]);

    $this->postJson('/api/v1/me/phone', ['phone' => '01512345678'])->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson('/api/v1/me/phone', ['phone' => '01512345678'])->assertCreated();
});

it('sends no code when a limit is reached', function (): void {
    PhoneVerification::factory()->for($this->user)->count(5)->create(['created_at' => Date::now()->subMinutes(1)]);

    $this->postJson('/api/v1/me/phone', ['phone' => '01512345678'])->assertStatus(429);

    expect(File::exists("{$this->logs}/phone-verification.log"))->toBeFalse()
        ->and(PhoneVerification::query()->count())->toBe(5);
});

it('describes the WhatsApp challenge step 4.3b\'s driver will give, under the same endpoint', function (): void {
    $challenge = PhoneChallenge::whatsapp('https://wa.me/201000000000?text=AWEZ-483920', Date::now()->addMinutes(10));

    expect(new PhoneChallengeResource($challenge)->resolve())->toBe([
        'method' => 'whatsapp',
        'expires_at' => '2026-11-25T18:10:00Z',
        'link' => 'https://wa.me/201000000000?text=AWEZ-483920',
    ]);
});
