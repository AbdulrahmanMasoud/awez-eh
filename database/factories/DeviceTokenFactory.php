<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Enums\DevicePlatform;
use App\Domains\Identity\Models\DeviceToken;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<DeviceToken>
 */
final class DeviceTokenFactory extends Factory
{
    #[\Override]
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // The access token of a device the user signed in on.
            'personal_access_token_id' => fn (array $attributes): int => User::query()->whereKey($attributes['user_id'])->firstOrFail()
                ->createToken('Pixel 8')->accessToken->id,
            'token' => fake()->regexify('[A-Za-z0-9_-]{22}:APA91b[A-Za-z0-9_-]{134}'),
            'platform' => DevicePlatform::Android,
            'app_version' => '1.0.0',
            'last_used_at' => Date::now(),
        ];
    }
}
