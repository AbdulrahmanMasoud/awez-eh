<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Actions\RecordUserActivity;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\AccountSuspended;
use App\Domains\Identity\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After auth:sanctum: a suspended account is refused everywhere, a deleted one (whose tokens are
 * already gone) is treated as signed out, and an active one's last_seen_at is kept fresh.
 */
final readonly class EnsureAccountIsActive
{
    public function __construct(private RecordUserActivity $recordActivity) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->status === UserStatus::Deleted) {
            throw new AuthenticationException;
        }

        if ($user->status === UserStatus::Suspended) {
            throw new AccountSuspended;
        }

        $this->recordActivity->handle($user);

        return $next($request);
    }
}
