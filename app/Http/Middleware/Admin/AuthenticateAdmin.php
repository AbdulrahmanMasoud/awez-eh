<?php

declare(strict_types=1);

namespace App\Http\Middleware\Admin;

use App\Domains\Identity\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;

/**
 * Filament's authentication, plus one rule: an admin suspended (or deleted) while signed in is
 * signed out on the next request and sent to the sign-in page, where the panel refuses them.
 * It extends Filament's middleware rather than running beside it, so Laravel's middleware
 * priority, which runs authentication early, keeps it in place.
 */
final class AuthenticateAdmin extends Authenticate
{
    /**
     * @param  Request|null  $request  Filament's signature leaves it untyped and optional.
     * @param  array<string>  $guards
     */
    protected function authenticate($request = null, array $guards = []): void
    {
        $request ??= request();
        $admin = Filament::auth()->user();

        if ($admin instanceof User && ! $admin->isActive()) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->unauthenticated($request, $guards);
        }

        parent::authenticate($request, $guards);
    }
}
