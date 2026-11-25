<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Every way an ID token can be wrong is this one error: the client learns nothing about which
 * check failed, and the log keeps the reason.
 */
final class InvalidIdentityToken extends DomainException
{
    public function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct('invalid_identity_token', 'errors.invalid_identity_token', Response::HTTP_UNAUTHORIZED, ['reason' => $reason], $previous);
    }
}
