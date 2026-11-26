<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Exceptions;

use App\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner has no confirmed number any more (they changed or removed it).
 */
final class ContactUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('contact_unavailable', 'errors.contact_unavailable', Response::HTTP_CONFLICT);
    }
}
