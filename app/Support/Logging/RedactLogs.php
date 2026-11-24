<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

/**
 * A log channel `tap` that adds RedactSensitiveData. Laravel adds its own Context processor
 * after the taps, and Monolog runs the last one added first, so the request id and the rest
 * of the context are already on the record when it is redacted.
 */
final class RedactLogs
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(new RedactSensitiveData);
        }
    }
}
