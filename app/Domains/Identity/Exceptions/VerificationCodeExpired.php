<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Exceptions\DomainException;

/**
 * No open confirmation: it expired, its attempts are used up, or none was started.
 */
final class VerificationCodeExpired extends DomainException
{
    public function __construct()
    {
        parent::__construct('verification_code_expired', 'errors.verification_code_expired');
    }
}
