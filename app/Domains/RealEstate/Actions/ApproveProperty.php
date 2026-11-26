<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\RealEstate\Models\Property;

/**
 * A moderator publishes a property waiting for review.
 */
final readonly class ApproveProperty
{
    public function __construct(private ChangePropertyStatus $changePropertyStatus) {}

    public function handle(Property $property, User $moderator): Property
    {
        return $this->changePropertyStatus->handle($property, ContentAction::Approve, $moderator);
    }
}
