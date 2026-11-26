<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\RealEstate\Data\PropertyContact;
use App\Domains\RealEstate\Exceptions\ContactThroughPlatform;
use App\Domains\RealEstate\Exceptions\ContactUnavailable;
use App\Domains\RealEstate\Models\Property;
use App\Support\PhoneNumber;

/**
 * A signed-in resident asks how to reach a property's owner. A brokered property's owner is never
 * reachable directly (invariant 2): the app opens a chat or books a viewing instead.
 */
final readonly class ContactPropertyOwner
{
    public function handle(Property $property): PropertyContact
    {
        if ($property->brokered) {
            throw new ContactThroughPlatform;
        }

        $phone = $property->user?->phone;

        if (! $phone instanceof PhoneNumber) {
            throw new ContactUnavailable;
        }

        return new PropertyContact($phone);
    }
}
