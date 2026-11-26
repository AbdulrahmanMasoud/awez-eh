<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Events;

/**
 * A moderator refused it, with a reason.
 */
final class PropertyRejected extends PropertyStatusChanged {}
