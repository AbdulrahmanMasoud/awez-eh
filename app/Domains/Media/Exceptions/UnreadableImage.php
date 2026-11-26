<?php

declare(strict_types=1);

namespace App\Domains\Media\Exceptions;

use App\Exceptions\DomainException;
use Throwable;

/**
 * The file claims an accepted format but cannot be decoded (cut short, corrupt, or another
 * format behind the right first bytes).
 */
final class UnreadableImage extends DomainException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('unreadable_image', 'errors.unreadable_image', previous: $previous);
    }
}
