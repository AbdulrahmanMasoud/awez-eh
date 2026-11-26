<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Actions\SyncAdminRoles;
use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * A resident who signed in with Google or Apple: no password (only admins have one, step 1.5)
 * and no confirmed number yet.
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    public const string ADMIN_PASSWORD = 'correct-horse-battery';

    /** A base32 secret, as an authenticator app holds it. */
    public const string AUTHENTICATOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    #[\Override]
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => Date::now(),
        ];
    }

    /**
     * Read back after creating, so a user has every column, as one loaded by a request does:
     * strict models throw on a column the factory did not set (status, locale, last_seen_at).
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (User $user): User => $user->refresh());
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * With a confirmed mobile number, a different one for each user.
     */
    public function withPhone(?string $phone = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'phone' => PhoneNumber::from($phone ?? '010'.fake()->unique()->numerify('########')),
            'phone_verified_at' => Date::now(),
        ]);
    }

    /**
     * An admin with these roles, a password, and an authenticator app set up (two-factor
     * authentication is required in the panel). Without roles, a super_admin.
     */
    public function admin(AdminRole ...$roles): static
    {
        return $this
            ->state(fn (array $attributes): array => [
                'password' => self::ADMIN_PASSWORD,
                'app_authentication_secret' => self::AUTHENTICATOR_SECRET,
            ])
            ->afterCreating(function (User $user) use ($roles): void {
                app(SyncAdminRoles::class)->handle();
                $user->assignRole(array_map(fn (AdminRole $role): string => $role->value, $roles === [] ? [AdminRole::SuperAdmin] : $roles));
            });
    }

    /**
     * An admin who has not set up an authenticator app yet.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => ['app_authentication_secret' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UserStatus::Suspended,
            'suspended_at' => Date::now(),
        ]);
    }
}
