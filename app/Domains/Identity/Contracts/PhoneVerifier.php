<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

use App\Domains\Identity\Data\PhoneChallenge;
use App\Domains\Identity\Enums\PhoneVerificationDriver;
use App\Domains\Identity\Models\User;
use App\Support\PhoneNumber;

/**
 * Starts confirming a number (ADR 0003). The `log` driver writes the code to its own log for the
 * resident to type; step 4.3b's `whatsapp_inbound` driver hands the app a pre-filled WhatsApp
 * message and confirms the number when our webhook receives it. The endpoints stay the same.
 * Rate limits and the number being taken are checked before a driver is asked.
 */
interface PhoneVerifier
{
    public function driver(): PhoneVerificationDriver;

    public function start(User $user, PhoneNumber $phone, string $ipAddress): PhoneChallenge;
}
