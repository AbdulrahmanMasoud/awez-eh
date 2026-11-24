<?php

declare(strict_types=1);

namespace App\Support\Logging;

use App\Support\PhoneNumber;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Keeps secrets out of every log line (CLAUDE.md invariant 8): values under secret-looking
 * keys are replaced, and Egyptian phone numbers written as plain text are masked. A
 * PhoneNumber object masks itself when the formatter turns it into a string.
 */
final class RedactSensitiveData implements ProcessorInterface
{
    public const string REDACTED = '[redacted]';

    /**
     * Matched case-insensitively against context keys. `code` alone is a verification code;
     * error_code and status_code stay readable.
     */
    private const string SECRET_KEY = '/password|token|secret|authorization|api_key|cookie|\A(?:code|otp|otp_code|verification_code)\z/i';

    /**
     * A mobile or landline number with its prefix, as one run of digits: 010…, 2010…, +2010…,
     * 002010…, 048…, +2048….
     */
    private const string PHONE_IN_TEXT = '/(?<!\d)'.PhoneNumber::PREFIX.'(?:'.PhoneNumber::MOBILE_DIGITS.'|'.PhoneNumber::LANDLINE_DIGITS.')(?!\d)/';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->maskPhoneNumbers($record->message),
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                is_string($key) && preg_match(self::SECRET_KEY, $key) === 1 => self::REDACTED,
                is_array($value) => $this->redact($value),
                is_string($value) => $this->maskPhoneNumbers($value),
                default => $value,
            };
        }

        return $values;
    }

    private function maskPhoneNumbers(string $text): string
    {
        return preg_replace_callback(
            self::PHONE_IN_TEXT,
            fn (array $match): string => PhoneNumber::fromMobileOrLandline($match[0])->mask(),
            $text,
        ) ?? $text;
    }
}
