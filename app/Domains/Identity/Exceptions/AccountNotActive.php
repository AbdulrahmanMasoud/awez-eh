<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended or deleted account cannot be made an admin: reactivate it first.
 */
final class AccountNotActive extends DomainException
{
    public function __construct()
    {
        parent::__construct('account_not_active', 'errors.account_not_active', Response::HTTP_CONFLICT);
    }
}
