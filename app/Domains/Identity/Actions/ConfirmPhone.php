<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\PhoneTaken;
use App\Domains\Identity\Exceptions\VerificationCodeExpired;
use App\Domains\Identity\Exceptions\VerificationCodeInvalid;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\PhoneCodeHasher;
use App\Exceptions\DomainException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Confirms the user's open confirmation with the code they typed. The number becomes the
 * user's in the same transaction that marks the confirmation used.
 */
final readonly class ConfirmPhone
{
    public function __construct(
        private PhoneCodeHasher $hasher,
        private Config $config,
    ) {}

    /**
     * @throws VerificationCodeInvalid
     * @throws VerificationCodeExpired
     * @throws PhoneTaken
     */
    public function handle(User $user, #[\SensitiveParameter] string $code): User
    {
        // A wrong code is answered after the transaction commits: throwing inside it would roll
        // back the attempt it just counted, and the attempts would never run out.
        $failure = DB::transaction(function () use ($user, $code): ?DomainException {
            $verification = $user->phoneVerifications()->open()->latest('id')->lockForUpdate()->first();
            $maxAttempts = $this->config->integer('awez.phone_verification.max_attempts');

            if ($verification === null || $verification->phone === null || $verification->attempts >= $maxAttempts) {
                return new VerificationCodeExpired;
            }

            if (! $this->hasher->matches($code, $verification->code_hash)) {
                $verification->increment('attempts');

                return new VerificationCodeInvalid($maxAttempts - $verification->attempts);
            }

            $phone = $verification->phone;

            if (User::query()->where('phone', $phone->e164())->whereKeyNot($user->getKey())->exists()) {
                throw new PhoneTaken;
            }

            $now = Date::now();
            $verification->forceFill(['confirmed_at' => $now])->save();

            try {
                $user->forceFill(['phone' => $phone, 'phone_verified_at' => $now])->save();
            } catch (UniqueConstraintViolationException $exception) {
                // Confirmed by another account between the check and now: the index is the last guard.
                throw new PhoneTaken($exception);
            }

            return null;
        });

        if ($failure !== null) {
            throw $failure;
        }

        return $user;
    }
}
