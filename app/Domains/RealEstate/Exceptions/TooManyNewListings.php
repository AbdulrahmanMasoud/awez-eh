<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Exceptions;

use App\Exceptions\DomainException;

/**
 * The account created as many listings in the last 24 hours as the settings allow.
 */
final class TooManyNewListings extends DomainException
{
    public function __construct(private readonly int $limit)
    {
        parent::__construct('too_many_new_listings', 'errors.too_many_new_listings');
    }

    public function errors(): array
    {
        return ['limit' => $this->limit];
    }
}
