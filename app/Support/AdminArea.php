<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

/**
 * Whether the current request is inside the admin panel. Admin permissions count only there
 * (`User::mayAdminister()`): an admin who also uses the app through the API is a resident
 * there, so a permission never widens what the API allows (DECISIONS 86).
 *
 * The panel's provider says how to tell (Filament's current panel); until it does, no request
 * is inside, so code outside the panel can never be granted an admin permission by accident.
 */
final class AdminArea
{
    /** @var (Closure(): bool)|null */
    private static ?Closure $detector = null;

    /**
     * @param  (Closure(): bool)|null  $detector
     */
    public static function detectUsing(?Closure $detector): void
    {
        self::$detector = $detector;
    }

    public static function isActive(): bool
    {
        return self::$detector instanceof Closure && (self::$detector)();
    }
}
