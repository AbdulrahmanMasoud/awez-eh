<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\ArrangeCollection;
use App\Domains\Media\Models\Media;
use App\Domains\Moderation\Exceptions\NotEditable;
use App\Domains\Moderation\Support\ContentLog;
use App\Domains\Moderation\Support\ContentStatusMachine;
use App\Domains\RealEstate\Data\PropertyChanges;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

/**
 * The owner edits their property. Changing only the price, or removing or reordering photos, keeps
 * a published property public; any other field or a new photo sends it back to review, hidden
 * until a moderator approves it (DECISIONS 108). Sold, rented and archived properties are not edited.
 */
final readonly class UpdateProperty
{
    /** The columns a change of price writes: alone, they need no review. */
    private const array PRICE_COLUMNS = ['price', 'currency'];

    public function __construct(
        private ArrangeCollection $arrangeCollection,
        private ContentStatusMachine $machine,
    ) {}

    public function handle(Property $property, User $owner, PropertyChanges $changes): Property
    {
        DB::transaction(function () use ($property, $owner, $changes): void {
            $locked = Property::query()->whereKey($property->id)->lockForUpdate()->sole();

            if (! $this->machine->isEditable($locked->status)) {
                throw new NotEditable;
            }

            $locked->fill($changes->attributes());

            if ($locked->isDirty('district_id')) {
                $locked->forceFill(['city_id' => District::query()->whereKey($locked->district_id)->value('city_id')]);
            }

            $fields = array_keys($locked->getDirty());
            [$photosChanged, $photosAdded] = $this->arrangePhotos($locked, $owner, $changes);

            if ($fields === [] && ! $photosChanged) {
                return;
            }

            $needsReview = $photosAdded || array_diff($fields, self::PRICE_COLUMNS) !== [];
            $from = $locked->status;
            $to = $this->machine->statusAfterEdit($from, $needsReview) ?? $from;

            $locked->forceFill([
                'status' => $to,
                'changed_since_review' => $locked->changed_since_review || $this->machine->marksChangeAfterEdit($from, $needsReview),
            ])->save();

            if ($to !== $from) {
                ContentLog::record(ContentLog::EDIT, $locked, $owner, false, $from, $to);
            }
        });

        return $property->refresh();
    }

    /**
     * @return array{bool, bool} Whether the photos changed at all, and whether new ones were added.
     */
    private function arrangePhotos(Property $property, User $owner, PropertyChanges $changes): array
    {
        if ($changes->photos instanceof Optional) {
            return [false, false];
        }

        $before = $this->photoIds($property);
        $added = $this->arrangeCollection->handle($property, Property::PHOTOS, $owner, $changes->photos);

        return [$added || $this->photoIds($property) !== $before, $added];
    }

    /**
     * @return list<string>
     */
    private function photoIds(Property $property): array
    {
        /** @var list<string> */
        return Media::query()
            ->where('model_type', $property->getMorphClass())
            ->where('model_id', $property->id)
            ->where('collection_name', Property::PHOTOS)
            ->orderBy('order_column')
            ->pluck('id')
            ->all();
    }
}
