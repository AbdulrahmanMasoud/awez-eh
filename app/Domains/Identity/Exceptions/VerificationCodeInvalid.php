<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Exceptions\DomainException;

final class VerificationCodeInvalid extends DomainException
{
    public function __construct(private readonly int $attemptsLeft)
    {
        parent::__construct('verification_code_invalid', 'errors.verification_code_invalid');
    }

    #[\Override]
    public function errors(): array
    {
        return ['attempts_left' => $this->attemptsLeft];
    }
}
