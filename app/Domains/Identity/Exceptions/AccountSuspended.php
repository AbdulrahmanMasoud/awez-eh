<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

final class AccountSuspended extends DomainException
{
    public function __construct()
    {
        parent::__construct('account_suspended', 'errors.account_suspended', Response::HTTP_FORBIDDEN);
    }
}
