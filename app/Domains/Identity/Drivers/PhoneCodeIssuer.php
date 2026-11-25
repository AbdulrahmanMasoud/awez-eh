<?php

declare(strict_types=1);

namespace App\Domains\Identity\Drivers;

use App\Domains\Identity\Data\IssuedPhoneCode;
use App\Domains\Identity\Enums\PhoneVerificationDriver;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\PhoneCodeHasher;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Date;

/**
 * Opens a confirmation: a random code, of which only the HMAC is stored. Drivers decide how the
 * code reaches the resident, or the resident reaches us with it.
 */
final readonly class PhoneCodeIssuer
{
    public function __construct(
        private PhoneCodeHasher $hasher,
        private int $codeLength,
        private int $expiresMinutes,
    ) {}

    public function issue(User $user, PhoneNumber $phone, PhoneVerificationDriver $driver, string $ipAddress): IssuedPhoneCode
    {
        // random_int is a CSPRNG; padding keeps codes like 004215 at full length.
        $code = str_pad((string) random_int(0, 10 ** $this->codeLength - 1), $this->codeLength, '0', STR_PAD_LEFT);

        $verification = $user->phoneVerifications()->create([
            'phone' => $phone,
            'driver' => $driver,
            'code_hash' => $this->hasher->hash($code),
            'expires_at' => Date::now()->addMinutes($this->expiresMinutes),
            'ip_address' => $ipAddress,
        ]);

        return new IssuedPhoneCode($verification, $code, $this->codeLength);
    }
}
