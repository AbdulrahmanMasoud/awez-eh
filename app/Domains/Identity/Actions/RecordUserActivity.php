<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Date;

/**
 * Writes `last_seen_at`, at most once every few minutes: a resident's every request would
 * otherwise write their row.
 */
final readonly class RecordUserActivity
{
    public function __construct(private Config $config) {}

    public function handle(User $user): void
    {
        $now = Date::now();
        $every = $this->config->integer('awez.accounts.last_seen_every_minutes');

        if ($user->last_seen_at !== null && $user->last_seen_at->greaterThan($now->subMinutes($every))) {
            return;
        }

        // Through the query builder: seeing a user is not changing their profile, so
        // updated_at stays.
        User::query()->whereKey($user->getKey())->toBase()->update(['last_seen_at' => $now]);

        $user->last_seen_at = $now;
        $user->syncOriginalAttribute('last_seen_at');
    }
}
