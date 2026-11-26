<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Geography\Models\District;
use App\Domains\Identity\Actions\EnsurePlatformAccount;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\RealEstate\Data\NewAgreement;
use App\Domains\RealEstate\Exceptions\PlatformAccountMissing;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * A sales admin lists a property whose owner signed an agreement: brokered from the start, owned
 * by the owner's account, or by the platform account when the owner has none. It waits for review
 * like any listing (invariant 9).
 */
final readonly class CreateBrokeredProperty
{
    public const string EVENT = 'brokered_property_created';

    public function __construct(private RecordPropertyAgreement $recordPropertyAgreement) {}

    /**
     * @param  array<string, mixed>  $attributes  The property's own columns, as NewProperty::attributes() names them.
     */
    public function handle(User $admin, array $attributes, ?User $owner, NewAgreement $agreement): Property
    {
        $owner ??= EnsurePlatformAccount::find() ?? throw new PlatformAccountMissing;

        return DB::transaction(function () use ($admin, $attributes, $owner, $agreement): Property {
            $property = new Property($attributes);
            $property->forceFill([
                'user_id' => $owner->id,
                'city_id' => District::query()->whereKey($property->district_id)->value('city_id'),
                'status' => ContentStatus::PendingReview,
            ])->save();

            AdminLog::record(self::EVENT, $property, $admin, after: ['status' => ContentStatus::PendingReview->value]);
            $this->recordPropertyAgreement->handle($property, $admin, $agreement);

            return $property->refresh();
        });
    }
}
