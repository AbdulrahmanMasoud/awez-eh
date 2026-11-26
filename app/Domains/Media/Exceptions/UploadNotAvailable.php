<?php

declare(strict_types=1);

namespace App\Domains\Media\Exceptions;

use App\Exceptions\DomainException;

/**
 * One of the uploads named does not exist, has expired or is someone else's. The three answer
 * the same, so an id says nothing about another resident's uploads.
 */
final class UploadNotAvailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('upload_not_available', 'errors.upload_not_available');
    }
}
