<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A brokered property for an owner without an account needs the platform account, which
 * `awez:platform-account` creates once.
 */
final class PlatformAccountMissing extends DomainException
{
    public function __construct()
    {
        parent::__construct('platform_account_missing', 'errors.platform_account_missing', Response::HTTP_CONFLICT);
    }
}
