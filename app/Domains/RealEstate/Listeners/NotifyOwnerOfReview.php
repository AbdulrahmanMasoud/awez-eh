<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Listeners;

use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\RealEstate\Events\PropertyApproved;
use App\Domains\RealEstate\Events\PropertyRejected;
use App\Domains\RealEstate\Events\PropertyTakenDown;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Notifications\PropertyReviewed;

/**
 * The owner hears of every review of their property: approved, rejected, or taken down.
 */
final class NotifyOwnerOfReview
{
    public function handle(PropertyApproved|PropertyRejected|PropertyTakenDown $event): void
    {
        $property = Property::query()->with('user')->find($event->propertyId);

        $property?->user?->notify(new PropertyReviewed($event->propertyId, match (true) {
            $event instanceof PropertyApproved => ContentAction::Approve,
            $event instanceof PropertyRejected => ContentAction::Reject,
            $event instanceof PropertyTakenDown => ContentAction::TakeDown,
        }, $event->reason));
    }
}
