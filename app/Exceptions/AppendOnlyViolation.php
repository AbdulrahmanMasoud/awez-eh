<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * Code tried to change or remove a row of an append-only table. A programming error, never shown
 * to a resident: the fix is to record a correcting row.
 */
final class AppendOnlyViolation extends LogicException
{
    public static function on(string $table, string $verb): self
    {
        return new self("[{$table}] is append-only: a row is never {$verb}. Record a correcting row instead.");
    }
}
