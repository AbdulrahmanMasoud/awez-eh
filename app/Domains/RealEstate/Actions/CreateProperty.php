<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\AttachUploads;
use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Support\ContentStatusMachine;
use App\Domains\RealEstate\Data\NewProperty;
use App\Domains\RealEstate\Exceptions\PhoneRequired;
use App\Domains\RealEstate\Exceptions\TooManyActiveListings;
use App\Domains\RealEstate\Exceptions\TooManyNewListings;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Settings\RealEstateSettings;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * A resident lists a property: with its photos from their uploads, as a draft or straight to review.
 * Within the account's limits (RealEstateSettings), counted under a lock on the account so two
 * requests at once cannot pass them together.
 */
final readonly class CreateProperty
{
    private const int DAY_IN_HOURS = 24;

    public function __construct(
        private AttachUploads $attachUploads,
        private ChangePropertyStatus $changePropertyStatus,
        private ContentStatusMachine $machine,
        private RealEstateSettings $settings,
    ) {}

    public function handle(User $owner, NewProperty $data): Property
    {
        if ($owner->needsPhone()) {
            throw new PhoneRequired;
        }

        return DB::transaction(function () use ($owner, $data): Property {
            User::query()->whereKey($owner->id)->lockForUpdate()->first();
            $this->checkLimits($owner);

            $property = new Property($data->attributes());
            $property->forceFill([
                'user_id' => $owner->id,
                'city_id' => District::query()->whereKey($data->districtId)->value('city_id'),
                'status' => ContentStatus::Draft,
            ])->save();

            $this->attachUploads->handle($property, Property::PHOTOS, $owner, $data->photos);

            return $data->submit
                ? $this->changePropertyStatus->handle($property, ContentAction::Submit, $owner)
                : $property;
        });
    }

    private function checkLimits(User $owner): void
    {
        $active = $owner->properties()
            ->whereNotIn('status', array_filter(ContentStatus::cases(), $this->machine->isTerminal(...)))
            ->count();

        if ($active >= $this->settings->active_listings_per_account) {
            throw new TooManyActiveListings($this->settings->active_listings_per_account);
        }

        $today = $owner->properties()->where('created_at', '>', Date::now()->subHours(self::DAY_IN_HOURS))->count();

        if ($today >= $this->settings->new_listings_per_day) {
            throw new TooManyNewListings($this->settings->new_listings_per_day);
        }
    }
}
