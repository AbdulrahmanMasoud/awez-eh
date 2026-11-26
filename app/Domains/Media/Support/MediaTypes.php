<?php

declare(strict_types=1);

namespace App\Domains\Media\Support;

/**
 * What a collection accepts, by content (finfo), never by extension. HEIC is not here: the
 * container's ImageMagick lists it but has no HEVC decoder, and the app always sends JPEG.
 */
final class MediaTypes
{
    public const string JPEG = 'image/jpeg';

    /** @var list<string> */
    public const array IMAGES = [self::JPEG, 'image/png', 'image/webp'];

    /** @var list<string> */
    public const array DOCUMENTS = [...self::IMAGES, 'application/pdf'];

    /** The decoder ImageMagick must use for each accepted type, so it never guesses one. */
    public const array DECODERS = [
        self::JPEG => 'JPEG',
        'image/png' => 'PNG',
        'image/webp' => 'WEBP',
    ];
}
