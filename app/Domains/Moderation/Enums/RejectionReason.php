<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Enums;

/**
 * The reasons moderators give most, in the reject form beside free text. The owner reads the
 * reason in their own language.
 */
enum RejectionReason: string
{
    case UnclearPhotos = 'unclear_photos';
    case MissingDetails = 'missing_details';
    case WrongPrice = 'wrong_price';
    case Duplicate = 'duplicate';
    case WrongCategory = 'wrong_category';
    case ContactInText = 'contact_in_text';
    case Other = 'other';

    public function label(?string $locale = null): string
    {
        return (string) __("moderation.rejection_reasons.{$this->value}", [], $locale);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $reason): string => $reason->label(), self::cases()),
        );
    }

    /**
     * What the owner reads: the common reason in their language, then the moderator's words.
     */
    public static function compose(?string $reason, ?string $details, string $locale): string
    {
        $common = self::tryFrom((string) $reason);
        $details = trim((string) $details);

        $parts = array_filter([
            $common instanceof self && $common !== self::Other ? $common->label($locale) : '',
            $details,
        ], fn (string $part): bool => $part !== '');

        return implode(' ', $parts);
    }
}
