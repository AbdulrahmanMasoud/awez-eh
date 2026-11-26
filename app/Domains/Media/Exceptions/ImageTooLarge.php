<?php

declare(strict_types=1);

namespace App\Domains\Media\Exceptions;

use App\Exceptions\DomainException;

/**
 * The image declares more pixels than any phone photo has: refused before it is decoded.
 */
final class ImageTooLarge extends DomainException
{
    public function __construct(int $width, int $height)
    {
        parent::__construct('image_too_large', 'errors.image_too_large', context: ['width' => $width, 'height' => $height]);
    }
}
