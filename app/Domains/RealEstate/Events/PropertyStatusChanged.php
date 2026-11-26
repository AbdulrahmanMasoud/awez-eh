<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Events;

use App\Domains\Moderation\Enums\ContentStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A property moved from one status to another (ChangePropertyStatus). One final class per action,
 * so listeners subscribe to what they care about. Ids, never models: listeners read fresh rows.
 */
abstract class PropertyStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    final public function __construct(
        public readonly string $propertyId,
        public readonly ContentStatus $from,
        public readonly ContentStatus $to,
        public readonly ?string $reason = null,
    ) {}
}
