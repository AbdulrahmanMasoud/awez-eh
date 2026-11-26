<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Identity\Contracts\PhoneVerifier;
use App\Domains\Identity\Drivers\AppleIdentityTokenVerifier;
use App\Domains\Identity\Drivers\GoogleIdentityTokenVerifier;
use App\Domains\Identity\Drivers\IdentityTokenVerifiers;
use App\Domains\Identity\Drivers\IdTokenDecoder;
use App\Domains\Identity\Drivers\JsonWebKeys;
use App\Domains\Identity\Drivers\LogPhoneVerifier;
use App\Domains\Identity\Drivers\PhoneCodeIssuer;
use App\Domains\Identity\Enums\PhoneVerificationDriver;
use App\Domains\Identity\Policies\RolePolicy;
use App\Domains\Identity\Support\PhoneCodeHasher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Spatie\Permission\Models\Role;

/**
 * Sign-in and phone confirmation (ADR 0003): the drivers, built from config/awez.php, and the
 * refusal to run a development-only driver anywhere but local and testing. The app serving
 * requests refuses at boot; anything else (a queue worker, a command) refuses when it resolves
 * a driver. Console commands that never touch one, such as Composer's package:discover during
 * an install that has no .env yet, still run.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    /** Where the fake sign-in and the `log` phone driver may run. */
    public const array DEVELOPMENT_ENVIRONMENTS = ['local', 'testing'];

    public function register(): void
    {
        $this->app->singleton(PhoneCodeHasher::class, fn (): PhoneCodeHasher => new PhoneCodeHasher(Config::string('app.key')));

        $this->app->bind(JsonWebKeys::class, fn (Application $app): JsonWebKeys => new JsonWebKeys(
            $app->make(HttpFactory::class),
            $app->make('cache.store'),
            Config::integer('awez.sign_in.keys_cache_seconds'),
        ));

        $this->app->bind(IdTokenDecoder::class, fn (Application $app): IdTokenDecoder => new IdTokenDecoder(
            $app->make(JsonWebKeys::class),
            Config::integer('awez.sign_in.leeway_seconds'),
        ));

        foreach (['google' => GoogleIdentityTokenVerifier::class, 'apple' => AppleIdentityTokenVerifier::class] as $provider => $verifier) {
            $this->app->bind($verifier, fn (Application $app): GoogleIdentityTokenVerifier|AppleIdentityTokenVerifier => new $verifier(
                $app->make(IdTokenDecoder::class),
                Config::string("awez.sign_in.{$provider}.keys_url"),
                $this->strings("awez.sign_in.{$provider}.client_ids"),
                $this->strings("awez.sign_in.{$provider}.issuers"),
            ));
        }

        $this->app->bind(function (Application $app): IdentityTokenVerifiers {
            $this->refuseDevelopmentDrivers();

            return new IdentityTokenVerifiers($app, Config::boolean('awez.sign_in.fake'));
        });

        $this->app->bind(PhoneCodeIssuer::class, fn (Application $app): PhoneCodeIssuer => new PhoneCodeIssuer(
            $app->make(PhoneCodeHasher::class),
            Config::integer('awez.phone_verification.code_length'),
            Config::integer('awez.phone_verification.expires_minutes'),
        ));

        $this->app->bind(function (Application $app): PhoneVerifier {
            $this->refuseDevelopmentDrivers();

            return match (PhoneVerificationDriver::from(Config::string('awez.phone_verification.driver'))) {
                PhoneVerificationDriver::Log => new LogPhoneVerifier(
                    $app->make(PhoneCodeIssuer::class),
                    $app->make(LogManager::class)->channel('phone_verification'),
                ),
                PhoneVerificationDriver::WhatsappInbound => throw new LogicException('The whatsapp_inbound phone verification driver comes with step 4.3b.'),
            };
        });
    }

    public function boot(): void
    {
        // Spatie's Role is not ours, so its policy is registered by hand (Shield's roles screen).
        Gate::policy(Role::class, RolePolicy::class);

        if (! $this->app->runningInConsole()) {
            $this->refuseDevelopmentDrivers();
        }
    }

    /**
     * A fake sign-in or codes written to a log would let anyone in: they never run where real
     * residents are.
     */
    public function refuseDevelopmentDrivers(): void
    {
        if ($this->app->environment(self::DEVELOPMENT_ENVIRONMENTS)) {
            return;
        }

        if (Config::boolean('awez.sign_in.fake')) {
            throw new LogicException('AWEZ_SIGN_IN_FAKE is for local and testing only.');
        }

        if (Config::string('awez.phone_verification.driver') === PhoneVerificationDriver::Log->value) {
            throw new LogicException('The log phone verification driver is for local and testing only; set AWEZ_PHONE_VERIFICATION_DRIVER.');
        }
    }

    /**
     * @return list<string>
     */
    private function strings(string $key): array
    {
        return array_values(array_filter(Config::array($key), is_string(...)));
    }
}
