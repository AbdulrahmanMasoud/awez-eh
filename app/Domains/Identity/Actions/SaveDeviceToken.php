<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\DevicePlatform;
use App\Domains\Identity\Models\DeviceToken;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Saves the push token of the device behind an access token, one per access token. A push token
 * names one app install: if another access token held it (the device signed in again, or now
 * belongs to someone else), that row goes, so pushes reach only the device's current account.
 */
final readonly class SaveDeviceToken
{
    public function handle(User $user, PersonalAccessToken $accessToken, string $token, DevicePlatform $platform, ?string $appVersion): DeviceToken
    {
        return DB::transaction(function () use ($user, $accessToken, $token, $platform, $appVersion): DeviceToken {
            $accessTokenId = $accessToken->getKey();

            DeviceToken::query()->where('token', $token)->where('personal_access_token_id', '<>', $accessTokenId)->delete();

            $device = DeviceToken::query()->where('personal_access_token_id', $accessTokenId)->first() ?? new DeviceToken;
            $device->forceFill([
                'user_id' => $user->getKey(),
                'personal_access_token_id' => $accessTokenId,
                'token' => $token,
                'platform' => $platform,
                'app_version' => $appVersion,
                'last_used_at' => Date::now(),
            ])->save();

            return $device;
        });
    }
}
