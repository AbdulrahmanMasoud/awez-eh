<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Events;

/**
 * A moderator took it off the public lists, with a reason.
 */
final class PropertyTakenDown extends PropertyStatusChanged {}
