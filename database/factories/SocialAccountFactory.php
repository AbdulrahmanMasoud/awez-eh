<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Enums\IdentityProvider;
use App\Domains\Identity\Models\SocialAccount;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<SocialAccount>
 */
final class SocialAccountFactory extends Factory
{
    #[\Override]
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => IdentityProvider::Google,
            // Google's subjects are long runs of digits.
            'subject' => fake()->unique()->numerify('1##################'),
            'email' => fake()->safeEmail(),
            'linked_at' => Date::now(),
        ];
    }

    public function apple(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => IdentityProvider::Apple,
            'subject' => fake()->unique()->numerify('######.').fake()->md5().'.'.fake()->numerify('####'),
        ]);
    }
}
