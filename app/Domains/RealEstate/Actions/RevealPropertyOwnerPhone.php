<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\RealEstate\Models\Property;
use App\Support\PhoneNumber;

/**
 * An admin looks at a property owner's full number: for a brokered property the number on its
 * latest agreement, otherwise the owner's account's. The log says who and when, never the number.
 */
final readonly class RevealPropertyOwnerPhone
{
    public const string EVENT = 'phone_revealed';

    public function handle(Property $property, User $admin): ?PhoneNumber
    {
        AdminLog::record(self::EVENT, $property, $admin);

        if ($property->brokered) {
            return $property->agreements()->latest('signed_on')->latest('id')->first()?->owner_phone;
        }

        return $property->user?->phone;
    }
}
