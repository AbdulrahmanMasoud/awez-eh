<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The last active super_admin cannot lose the role, be suspended or delete the account: nobody
 * would be left to manage roles.
 */
final class LastSuperAdmin extends DomainException
{
    public function __construct()
    {
        parent::__construct('last_super_admin', 'errors.last_super_admin', Response::HTTP_CONFLICT);
    }
}
