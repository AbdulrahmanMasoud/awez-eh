<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\Moderation\Enums\ContentActor;
use App\Domains\Moderation\Exceptions\ActionNotAllowed;
use App\Domains\Moderation\Exceptions\ReasonRequired;
use App\Domains\Moderation\Support\ContentLog;
use App\Domains\Moderation\Support\ContentStatusMachine;
use App\Domains\RealEstate\Events\PropertyApproved;
use App\Domains\RealEstate\Events\PropertyArchived;
use App\Domains\RealEstate\Events\PropertyPaused;
use App\Domains\RealEstate\Events\PropertyRejected;
use App\Domains\RealEstate\Events\PropertyRented;
use App\Domains\RealEstate\Events\PropertyResumed;
use App\Domains\RealEstate\Events\PropertySold;
use App\Domains\RealEstate\Events\PropertyStatusChanged;
use App\Domains\RealEstate\Events\PropertySubmitted;
use App\Domains\RealEstate\Events\PropertyTakenDown;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Moves a property as ContentStatusMachine allows, under a row lock, with the activity entry and the
 * event after commit. The named Actions (SubmitProperty, ApproveProperty, ...) call it; who may act
 * at all is the caller's policy check.
 */
final readonly class ChangePropertyStatus
{
    public function __construct(private ContentStatusMachine $machine) {}

    public function handle(Property $property, ContentAction $action, User $by, ?string $reason = null): Property
    {
        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);

        if ($this->machine->requiresReason($action) && $reason === null) {
            throw new ReasonRequired;
        }

        DB::transaction(function () use ($property, $action, $by, $reason): void {
            $locked = Property::query()->whereKey($property->id)->lockForUpdate()->sole();
            $actor = $this->machine->actorOf($action);
            $state = $locked->contentState();
            $to = $this->machine->next($state, $action, $actor)
                ?? throw new ActionNotAllowed($this->machine->allowedActions($state, $actor));
            $from = $locked->status;

            $locked->forceFill([...$this->columns($action, $by, $reason, $locked), 'status' => $to])->save();

            ContentLog::record($action->value, $locked, $by, $actor === ContentActor::Moderator, $from, $to, $reason);

            $event = $this->eventFor($action);
            event(new $event($locked->id, $from, $to, $reason));
        });

        return $property->refresh();
    }

    /**
     * What else changes with the status: a review clears the "changed" flag and records who and
     * when; a first approval stamps the publication date, which later approvals keep.
     *
     * @return array<string, mixed>
     */
    private function columns(ContentAction $action, User $by, ?string $reason, Property $property): array
    {
        $now = Date::now();

        return match ($action) {
            ContentAction::Approve => [
                'reviewed_by' => $by->id,
                'reviewed_at' => $now,
                'published_at' => $property->published_at ?? $now,
                'rejection_reason' => null,
                'changed_since_review' => false,
            ],
            ContentAction::Reject, ContentAction::TakeDown => [
                'reviewed_by' => $by->id,
                'reviewed_at' => $now,
                'rejection_reason' => $reason,
                'changed_since_review' => false,
            ],
            // Back in the queue: the changes are what the moderator now reviews.
            ContentAction::Submit, ContentAction::Resume => ['changed_since_review' => false],
            ContentAction::Pause, ContentAction::MarkSold, ContentAction::MarkRented, ContentAction::Archive => [],
        };
    }

    /**
     * @return class-string<PropertyStatusChanged>
     */
    private function eventFor(ContentAction $action): string
    {
        return match ($action) {
            ContentAction::Submit => PropertySubmitted::class,
            ContentAction::Approve => PropertyApproved::class,
            ContentAction::Reject => PropertyRejected::class,
            ContentAction::TakeDown => PropertyTakenDown::class,
            ContentAction::Pause => PropertyPaused::class,
            ContentAction::Resume => PropertyResumed::class,
            ContentAction::MarkSold => PropertySold::class,
            ContentAction::MarkRented => PropertyRented::class,
            ContentAction::Archive => PropertyArchived::class,
        };
    }
}
