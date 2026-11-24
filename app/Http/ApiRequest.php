<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;

/**
 * Whether a request is for the API, decided by its path. The API's middleware runs globally
 * and asks this, so even a 404 for an unknown route or a 405 answers as the API does.
 */
final class ApiRequest
{
    public static function matches(Request $request): bool
    {
        return $request->is('api', 'api/*');
    }
}
