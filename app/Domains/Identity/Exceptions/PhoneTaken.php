<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Another account has confirmed this number. Says nothing about which account.
 */
final class PhoneTaken extends DomainException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('phone_taken', 'errors.phone_taken', Response::HTTP_CONFLICT, previous: $previous);
    }
}
