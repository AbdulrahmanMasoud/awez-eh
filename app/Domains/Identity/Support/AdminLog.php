<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;

/**
 * One entry in the admin activity log: who did what to which record, why, and what changed.
 * Never personal data: statuses and roles, not names, emails or numbers.
 */
final class AdminLog
{
    public const string NAME = 'admin';

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function record(string $event, Model $subject, ?User $admin, ?string $reason = null, array $before = [], array $after = []): ?Activity
    {
        $logger = activity(self::NAME)
            ->performedOn($subject)
            ->causedBy($admin)
            ->event($event)
            ->withProperties(array_filter(['reason' => $reason], fn (?string $value): bool => $value !== null));

        if ($before !== [] || $after !== []) {
            $logger->withChanges(['old' => $before, 'attributes' => $after]);
        }

        return $logger->log($event);
    }
}
