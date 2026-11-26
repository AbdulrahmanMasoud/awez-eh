<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Listing a property needs a confirmed number: it is how residents reach the owner.
 */
final class PhoneRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('phone_required', 'errors.phone_required', Response::HTTP_FORBIDDEN);
    }
}
