<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Enums\PhoneVerificationDriver;
use App\Domains\Identity\Models\PhoneVerification;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\PhoneCodeHasher;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

/**
 * An open confirmation whose code is 123456.
 *
 * @extends Factory<PhoneVerification>
 */
final class PhoneVerificationFactory extends Factory
{
    public const string CODE = '123456';

    #[\Override]
    protected $model = PhoneVerification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone' => PhoneNumber::from('010'.fake()->numerify('########')),
            'driver' => PhoneVerificationDriver::Log,
            'code_hash' => new PhoneCodeHasher(Config::string('app.key'))->hash(self::CODE),
            'expires_at' => Date::now()->addMinutes(10),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
