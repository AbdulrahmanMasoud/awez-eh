<?php

declare(strict_types=1);

namespace App\Domains\Media\Exceptions;

use App\Exceptions\DomainException;

/**
 * The collection would hold more files than its limit (MediaSettings).
 */
final class TooManyFiles extends DomainException
{
    public function __construct(private readonly int $limit)
    {
        parent::__construct('too_many_files', 'errors.too_many_files');
    }

    public function errors(): array
    {
        return ['limit' => $this->limit];
    }
}
