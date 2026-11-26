<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Exceptions;

use App\Exceptions\DomainException;

/**
 * The account already has as many listings that are not sold, rented or archived as the settings allow.
 */
final class TooManyActiveListings extends DomainException
{
    public function __construct(private readonly int $limit)
    {
        parent::__construct('too_many_active_listings', 'errors.too_many_active_listings');
    }

    public function errors(): array
    {
        return ['limit' => $this->limit];
    }
}
