<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Contracts\PhoneVerifier;
use App\Domains\Identity\Data\PhoneChallenge;
use App\Domains\Identity\Exceptions\PhoneTaken;
use App\Domains\Identity\Exceptions\TooManyAttempts;
use App\Domains\Identity\Models\PhoneVerification;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\RateLimit;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Starts confirming a number: refused if another account has confirmed it or a rate limit is
 * reached (per number, per user, per address), and any open confirmation of the user's is
 * replaced. The rate limits count the rows themselves, so a replaced start still counts.
 */
final readonly class StartPhoneVerification
{
    public function __construct(
        private PhoneVerifier $verifier,
        private Config $config,
    ) {}

    /**
     * @throws PhoneTaken
     * @throws TooManyAttempts
     */
    public function handle(User $user, PhoneNumber $phone, string $ipAddress): PhoneChallenge
    {
        return DB::transaction(function () use ($user, $phone, $ipAddress): PhoneChallenge {
            // One start at a time per user, so two at once cannot both slip under a limit.
            User::query()->whereKey($user->getKey())->lockForUpdate()->value('id');

            if (User::query()->where('phone', $phone->e164())->whereKeyNot($user->getKey())->exists()) {
                throw new PhoneTaken;
            }

            $now = Date::now();
            $this->enforceRateLimits($user, $phone, $ipAddress, $now);

            $user->phoneVerifications()->open($now)->update(['expires_at' => $now]);

            return $this->verifier->start($user, $phone, $ipAddress);
        });
    }

    private function enforceRateLimits(User $user, PhoneNumber $phone, string $ipAddress, CarbonImmutable $now): void
    {
        $scopes = [
            'phone' => ['phone', $phone->e164()],
            'user' => ['user_id', $user->getKey()],
            'ip' => ['ip_address', $ipAddress],
        ];

        $retryAfter = null;
        $reached = null;

        foreach ($scopes as $scope => [$column, $value]) {
            /** @var list<array{max: int, minutes: int}> $limits */
            $limits = $this->config->array("awez.phone_verification.rate_limits.{$scope}");

            foreach ($limits as $limitConfig) {
                $limit = RateLimit::fromConfig($limitConfig);
                $seconds = $limit->retryAfterSeconds($this->recentStarts($column, $value, $limit, $now), $now);

                if ($seconds !== null && $seconds > ($retryAfter ?? 0)) {
                    $retryAfter = $seconds;
                    $reached = "{$scope}: {$limit->max} in {$limit->minutes} minutes";
                }
            }
        }

        if ($retryAfter !== null && $reached !== null) {
            throw new TooManyAttempts($retryAfter, $reached);
        }
    }

    /**
     * The latest starts in the limit's window: no more than it allows are needed.
     *
     * @return list<CarbonImmutable>
     */
    private function recentStarts(string $column, mixed $value, RateLimit $limit, CarbonImmutable $now): array
    {
        $starts = PhoneVerification::query()
            ->where($column, $value)
            ->where('created_at', '>', $limit->windowStart($now))
            ->latest()
            ->limit($limit->max)
            ->get(['created_at'])
            ->map(fn (PhoneVerification $verification): CarbonImmutable => $verification->created_at ?? $now)
            ->all();

        return array_values($starts);
    }
}
