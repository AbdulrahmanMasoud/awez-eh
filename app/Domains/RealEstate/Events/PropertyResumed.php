<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Events;

/**
 * Its owner showed it again, or sent it back to review after an edit.
 */
final class PropertyResumed extends PropertyStatusChanged {}
