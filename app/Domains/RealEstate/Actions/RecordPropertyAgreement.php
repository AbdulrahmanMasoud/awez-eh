<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\RealEstate\Data\NewAgreement;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use Illuminate\Support\Facades\DB;

/**
 * A sales admin records the agreement the owner signed: the property becomes brokered, so its
 * owner's number leaves every public response (invariant 2). Agreements are never edited or
 * deleted. The log names the property, never the owner's name or number.
 */
final readonly class RecordPropertyAgreement
{
    public const string EVENT = 'agreement_recorded';

    public function handle(Property $property, User $admin, NewAgreement $data): PropertyAgreement
    {
        return DB::transaction(function () use ($property, $admin, $data): PropertyAgreement {
            $agreement = new PropertyAgreement($data->attributes());
            $agreement->forceFill(['property_id' => $property->id, 'created_by' => $admin->id])->save();

            $wasBrokered = $property->brokered;

            if (! $wasBrokered) {
                $property->forceFill(['brokered' => true])->save();
            }

            AdminLog::record(self::EVENT, $property, $admin, before: ['brokered' => $wasBrokered], after: ['brokered' => true]);

            return $agreement;
        });
    }
}
