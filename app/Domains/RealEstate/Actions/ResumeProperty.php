<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Enums\ContentAction;
use App\Domains\RealEstate\Models\Property;

/**
 * The owner shows a paused property again: straight back if unchanged, through review if edited while paused.
 */
final readonly class ResumeProperty
{
    public function __construct(private ChangePropertyStatus $changePropertyStatus) {}

    public function handle(Property $property, User $owner): Property
    {
        return $this->changePropertyStatus->handle($property, ContentAction::Resume, $owner);
    }
}
