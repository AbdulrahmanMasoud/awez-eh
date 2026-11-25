<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * How a number is confirmed (ADR 0003): `log` while building, `whatsapp_inbound` from step 4.3b.
 */
enum PhoneVerificationDriver: string
{
    case Log = 'log';
    case WhatsappInbound = 'whatsapp_inbound';
}
