<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\RealEstate\Models\Property;

/**
 * The owner sends a draft, or a rejected property they changed, to review.
 */
final readonly class SubmitProperty
{
    public function __construct(private ChangePropertyStatus $changePropertyStatus) {}

    public function handle(Property $property, User $owner): Property
    {
        return $this->changePropertyStatus->handle($property, ContentAction::Submit, $owner);
    }
}
