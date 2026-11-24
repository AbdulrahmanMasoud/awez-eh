<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Carries no context on purpose: the rejected input may well be a real number.
 */
final class InvalidPhoneNumber extends DomainException
{
    public function __construct()
    {
        parent::__construct('invalid_phone_number', 'errors.invalid_phone_number');
    }
}
