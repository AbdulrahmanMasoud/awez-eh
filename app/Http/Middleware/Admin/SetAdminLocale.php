<?php

declare(strict_types=1);

namespace App\Http\Middleware\Admin;

use App\Domains\Identity\Models\User;
use App\Support\AdminNumbers;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel speaks the admin's language (`users.locale`), Arabic before signing in. Filament's
 * Arabic translations turn the layout right to left; numbers keep Western digits.
 */
final class SetAdminLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Filament::auth()->user();

        App::setLocale($admin instanceof User ? $admin->locale->value : Config::string('app.locale'));
        // Numbers and money in Western digits whatever the language (DECISIONS 97).
        Number::useLocale(AdminNumbers::localeFor(App::getLocale()));

        return $next($request);
    }
}
