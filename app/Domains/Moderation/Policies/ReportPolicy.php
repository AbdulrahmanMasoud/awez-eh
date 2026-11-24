<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Models\Report;

/**
 * What a resident may do with a report through the API. Admins act in Filament, under its
 * own rules (step 1.5).
 */
final class ReportPolicy
{
    /**
     * Only the resident who reported sees a report.
     */
    public function view(?User $user, Report $report): bool
    {
        return $report->user()->is($user);
    }

    public function update(User $user, Report $report): bool
    {
        return $report->user()->is($user);
    }

    public function delete(User $user, Report $report): bool
    {
        return $report->user()->is($user);
    }
}
