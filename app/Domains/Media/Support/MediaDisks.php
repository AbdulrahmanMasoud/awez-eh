<?php

declare(strict_types=1);

namespace App\Domains\Media\Support;

use Illuminate\Support\Facades\Config;

/**
 * The disks media goes to, from configuration only: no code names a disk (an arch test checks it),
 * so moving to R2 is a change of `.env`.
 */
final class MediaDisks
{
    /** Photos, served by URL. */
    public static function public(): string
    {
        return Config::string('awez.media.disk');
    }

    /** Documents that leave only as signed URLs. */
    public static function private(): string
    {
        return Config::string('awez.media.private_disk');
    }
}
