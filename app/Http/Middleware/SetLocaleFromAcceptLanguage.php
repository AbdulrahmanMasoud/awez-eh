<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\ApiRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API answers in the language the client prefers among config('awez.locales'), and in
 * the default locale (Arabic) otherwise.
 */
final class SetLocaleFromAcceptLanguage
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! ApiRequest::matches($request)) {
            return $next($request);
        }

        App::setLocale($this->preferredLocale($request));

        $response = $next($request);
        $response->headers->set('Content-Language', App::getLocale());

        return $response;
    }

    private function preferredLocale(Request $request): string
    {
        $default = Config::string('app.locale');
        $supported = array_filter(Config::array('awez.locales'), is_string(...));

        // Symfony returns the first locale when the header names none of them, so the default goes first.
        $preferred = $request->getPreferredLanguage([$default, ...array_diff($supported, [$default])]);

        return $preferred ?? $default;
    }
}
