<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\SignedIn;
use App\Domains\Identity\Data\VerifiedIdentity;
use App\Domains\Identity\Drivers\IdentityTokenVerifiers;
use App\Domains\Identity\Enums\IdentityProvider;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\AccountSuspended;
use App\Domains\Identity\Exceptions\InvalidIdentityToken;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a device in with a Google or Apple ID token. The user is the one linked to the token's
 * subject; else the active user with the same verified email, who gets this provider linked;
 * else a new user. A deleted account's links are gone, so its person simply becomes a new user.
 * Each device has one token: signing in again under the same device name replaces it.
 */
final readonly class SignIn
{
    private const int MAX_NAME_LENGTH = 255;

    public function __construct(private IdentityTokenVerifiers $verifiers) {}

    /**
     * @param  string|null  $name  What the app sends: Apple gives the name to the app, on the first sign-in only.
     *
     * @throws InvalidIdentityToken
     * @throws AccountSuspended
     */
    public function handle(IdentityProvider $provider, #[\SensitiveParameter] string $idToken, ?string $nonce, ?string $name, string $deviceName): SignedIn
    {
        $identity = $this->verifiers->for($provider)->verify($idToken, $nonce);

        return DB::transaction(function () use ($identity, $name, $deviceName): SignedIn {
            [$user, $isNewUser] = $this->findOrCreateUser($identity, $name);

            if (! $user->isActive()) {
                throw new AccountSuspended;
            }

            // Its push token goes with it (device_tokens cascades).
            $user->tokens()->where('name', $deviceName)->delete();

            return new SignedIn($user, $user->createToken($deviceName)->plainTextToken, $isNewUser);
        });
    }

    /**
     * @return array{User, bool}
     */
    private function findOrCreateUser(VerifiedIdentity $identity, ?string $name): array
    {
        $linked = User::query()
            ->whereHas('socialAccounts', fn ($query) => $query->where('provider', $identity->provider)->where('subject', $identity->subject))
            ->first();

        if ($linked instanceof User) {
            return [$linked, false];
        }

        $email = $identity->emailVerified && $identity->email !== null ? Str::lower($identity->email) : null;
        $sameEmail = $email === null ? null : User::query()->where('email', $email)->first();

        if ($sameEmail instanceof User && $sameEmail->status === UserStatus::Suspended) {
            throw new AccountSuspended;
        }

        if ($sameEmail instanceof User && ! $sameEmail->socialAccounts()->where('provider', $identity->provider)->exists()) {
            $this->link($sameEmail, $identity);

            return [$sameEmail, false];
        }

        // An email already taken by an account this provider cannot be linked to stays off the new one.
        $user = $this->createUser($identity, $sameEmail === null ? $email : null, $name);
        $this->link($user, $identity);

        return [$user, true];
    }

    private function createUser(VerifiedIdentity $identity, ?string $email, ?string $name): User
    {
        $displayName = Str::limit(trim($name ?? '') ?: trim($identity->name ?? '') ?: __('identity.default_name'), self::MAX_NAME_LENGTH, '');

        $user = new User;
        $user->forceFill([
            'name' => $displayName,
            'email' => $email,
            'email_verified_at' => $email === null ? null : Date::now(),
            'avatar_url' => $identity->avatarUrl,
        ])->save();

        return $user;
    }

    private function link(User $user, VerifiedIdentity $identity): void
    {
        $user->socialAccounts()->create([
            'provider' => $identity->provider,
            'subject' => $identity->subject,
            'email' => $identity->email,
            'linked_at' => Date::now(),
        ]);
    }
}
