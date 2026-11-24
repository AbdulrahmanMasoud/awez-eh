<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\ApiRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API answers in JSON whatever the client sends as Accept, so Laravel never answers an
 * API client with a redirect or an HTML page. Arabic is sent as UTF-8, not as \u escapes,
 * which take three times the bytes.
 */
final class ForceJsonResponse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! ApiRequest::matches($request)) {
            return $next($request);
        }

        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        }

        return $response;
    }
}
