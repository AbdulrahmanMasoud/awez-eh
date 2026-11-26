<?php

declare(strict_types=1);

namespace App\Domains\Media\Support;

use Illuminate\Support\Facades\Config;

/**
 * The two WebP sizes of every public photo; their widths are in config('awez.media.conversions').
 */
final class MediaConversions
{
    /** For lists. */
    public const string THUMB = 'thumb';

    /** For a listing's own page. */
    public const string LARGE = 'large';

    public const array ALL = [self::THUMB, self::LARGE];

    public static function width(string $conversion): int
    {
        return Config::integer("awez.media.conversions.{$conversion}");
    }
}
