<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Exceptions;

use App\Exceptions\DomainException;

/**
 * A rejection or a take-down without a reason: the owner must be told why.
 */
final class ReasonRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('reason_required', 'errors.reason_required');
    }
}
