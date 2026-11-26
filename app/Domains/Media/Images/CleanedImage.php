<?php

declare(strict_types=1);

namespace App\Domains\Media\Images;

/**
 * A clean JPEG written to a temporary file, ready to be stored. Medialibrary moves the file
 * when it stores it.
 */
final readonly class CleanedImage
{
    public function __construct(
        public string $path,
        public int $width,
        public int $height,
    ) {}
}
