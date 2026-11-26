<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\RealEstate\Models\Property;

/**
 * A moderator takes a published property down; the reason is sent to the owner.
 */
final readonly class TakeDownProperty
{
    public function __construct(private ChangePropertyStatus $changePropertyStatus) {}

    public function handle(Property $property, User $moderator, string $reason): Property
    {
        return $this->changePropertyStatus->handle($property, ContentAction::TakeDown, $moderator, $reason);
    }
}
