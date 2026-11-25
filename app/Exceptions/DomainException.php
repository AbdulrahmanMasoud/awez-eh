<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * An error the client can act on: rendered as {message, code, errors} under /api/*, and
 * not reported to the error tracker. Domains extend it in their own Exceptions/ folder.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param  string  $errorCode  Stable snake_case code the mobile app switches on.
     * @param  string  $translationKey  The message shown to the client, in its language.
     * @param  array<string, mixed>  $context  For logs only; never sent to clients.
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly string $translationKey,
        public readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, 0, $previous);
    }

    /**
     * Sent to the client under `errors`, as validation messages are: details it can act on.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return [];
    }

    /**
     * Headers for the response, such as Retry-After.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * Laravel adds this to the log entry if the exception is ever logged.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
