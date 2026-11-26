<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Events;

/**
 * Its owner sent it to review.
 */
final class PropertySubmitted extends PropertyStatusChanged {}
