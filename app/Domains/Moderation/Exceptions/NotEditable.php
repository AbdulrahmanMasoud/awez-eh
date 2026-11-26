<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sold, rented and archived content is history: it is not edited any more.
 */
final class NotEditable extends DomainException
{
    public function __construct()
    {
        parent::__construct('not_editable', 'errors.not_editable', Response::HTTP_CONFLICT);
    }
}
