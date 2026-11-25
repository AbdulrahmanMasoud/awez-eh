<?php

declare(strict_types=1);

use App\Providers\IdentityServiceProvider;

/**
 * Boots the provider's check as if the app ran in another environment.
 */
function refuseDriversIn(string $environment): void
{
    app()->detectEnvironment(fn (): string => $environment);

    new IdentityServiceProvider(app())->refuseDevelopmentDrivers();
}

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
});

it('refuses to boot with the fake sign-in outside local and testing', function (string $environment): void {
    config(['awez.sign_in.fake' => true, 'awez.phone_verification.driver' => 'whatsapp_inbound']);

    expect(fn () => refuseDriversIn($environment))->toThrow(LogicException::class, 'AWEZ_SIGN_IN_FAKE is for local and testing only.');
})->with(['production', 'staging']);

it('refuses to boot with the log phone driver outside local and testing', function (): void {
    config(['awez.sign_in.fake' => false, 'awez.phone_verification.driver' => 'log']);

    expect(fn () => refuseDriversIn('production'))->toThrow(LogicException::class, 'The log phone verification driver is for local and testing only');
});

it('boots with both in local and testing', function (string $environment): void {
    config(['awez.sign_in.fake' => true, 'awez.phone_verification.driver' => 'log']);

    refuseDriversIn($environment);

    expect(app()->environment())->toBe($environment);
})->with(['local', 'testing']);

it('boots in production with neither', function (): void {
    config(['awez.sign_in.fake' => false, 'awez.phone_verification.driver' => 'whatsapp_inbound']);

    refuseDriversIn('production');

    expect(app()->environment())->toBe('production');
});

it('keeps the fake sign-in off and the log driver on by default', function (): void {
    // config/awez.php as it reads with neither variable set, whatever this machine's .env says.
    $names = ['AWEZ_SIGN_IN_FAKE', 'AWEZ_PHONE_VERIFICATION_DRIVER'];
    $saved = array_map(getenv(...), array_combine($names, $names));

    foreach ($names as $name) {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }

    $config = require config_path('awez.php');

    foreach ($saved as $name => $value) {
        if (is_string($value)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    }

    expect($config['sign_in']['fake'])->toBeFalse()
        ->and($config['phone_verification']['driver'])->toBe('log');
});
