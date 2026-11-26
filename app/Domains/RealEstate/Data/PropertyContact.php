<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Data;

use App\Support\PhoneNumber;
use Spatie\LaravelData\Data;

/**
 * How to reach the owner of a property the platform does not broker.
 */
final class PropertyContact extends Data
{
    public function __construct(public readonly PhoneNumber $phone) {}

    /** WhatsApp's own link: the number in international form, digits only. */
    public function whatsappUrl(): string
    {
        return 'https://wa.me/'.ltrim($this->phone->e164(), '+');
    }
}
