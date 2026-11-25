<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A phone confirmation rate limit is reached. Which one stays in the log.
 */
final class TooManyAttempts extends DomainException
{
    public function __construct(private readonly int $retryAfterSeconds, string $limit)
    {
        parent::__construct('too_many_attempts', 'errors.too_many_attempts', Response::HTTP_TOO_MANY_REQUESTS, ['limit' => $limit]);
    }

    #[\Override]
    public function headers(): array
    {
        return ['Retry-After' => (string) $this->retryAfterSeconds];
    }
}
