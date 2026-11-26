<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Alerts\Actions\DeleteAccountSearchAlerts;
use App\Domains\Favorites\Actions\DeleteAccountFavorites;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\SuperAdmins;
use App\Domains\Marketplace\Actions\RemoveAccountUsedItems;
use App\Domains\Moderation\Actions\WithdrawAccountReports;
use App\Domains\Places\Actions\ReleaseAccountPlaces;
use App\Domains\RealEstate\Actions\RemoveAccountProperties;
use App\Domains\Requests\Actions\RemoveAccountRequests;
use App\Domains\Services\Actions\ArchiveAccountProvider;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an account by anonymizing it: the row always stays (DECISIONS 41, 56 and 72), because
 * conversations, bookings, reviews and leads point at it. Its sign-in, its devices and its
 * personal data go; each domain archives or deletes its own rows through its own Action.
 */
final readonly class DeleteAccount
{
    public function __construct(
        private RemoveAccountProperties $properties,
        private ArchiveAccountProvider $provider,
        private ReleaseAccountPlaces $places,
        private RemoveAccountUsedItems $usedItems,
        private RemoveAccountRequests $requests,
        private DeleteAccountFavorites $favorites,
        private DeleteAccountSearchAlerts $searchAlerts,
        private WithdrawAccountReports $reports,
        private SuperAdmins $superAdmins,
        private Config $config,
    ) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->superAdmins->ensureAnotherRemainsWithout($user);

            // Device tokens go with the access tokens too; deleted here so nothing depends on that.
            $user->deviceTokens()->delete();
            $user->tokens()->delete();
            $user->socialAccounts()->delete();
            $user->phoneVerifications()->delete();
            // An admin account stops being one: no role, no authenticator.
            $user->syncRoles([]);

            $this->properties->handle($user);
            $this->provider->handle($user);
            $this->places->handle($user);
            $this->usedItems->handle($user);
            $this->requests->handle($user);
            $this->favorites->handle($user);
            $this->searchAlerts->handle($user);
            $this->reports->handle($user);

            $this->anonymize($user);
        });
    }

    private function anonymize(User $user): void
    {
        $user->forceFill([
            // Stored in the default locale: the name is data, read by whoever looks (DECISIONS 72).
            'name' => __('identity.deleted_account_name', [], $this->config->string('app.locale')),
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
            'remember_token' => null,
            'phone' => null,
            'phone_verified_at' => null,
            'avatar_url' => null,
            'district_id' => null,
            'status' => UserStatus::Deleted,
            'suspended_at' => null,
            'deleted_at' => Date::now(),
        ])->save();
    }
}
