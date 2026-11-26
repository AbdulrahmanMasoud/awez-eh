<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A brokered property's owner is reached through the platform only (invariant 2): the app opens a
 * chat or books a viewing instead (steps 2.8 and 3.1).
 */
final class ContactThroughPlatform extends DomainException
{
    public function __construct()
    {
        parent::__construct('contact_through_platform', 'errors.contact_through_platform', Response::HTTP_CONFLICT);
    }
}
