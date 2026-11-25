<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * What the app does after starting a confirmation: ask for the code, or open WhatsApp with a
 * pre-filled message and poll GET me until the number is confirmed (step 4.3b).
 */
enum PhoneChallengeMethod: string
{
    case Code = 'code';
    case Whatsapp = 'whatsapp';
}
