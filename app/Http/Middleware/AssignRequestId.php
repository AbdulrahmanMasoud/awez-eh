<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id that Laravel's Context adds to each log line and to the jobs the
 * request queues, and echoes it in the response so a resident's report can be traced.
 */
final class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    /**
     * An id from the proxy or the app is kept when it is a plain token: letters, digits, dots,
     * underscores and dashes, at most 64 characters (a UUID is 36).
     */
    private const string SAFE_ID = '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);
        $requestId = is_string($incoming) && preg_match(self::SAFE_ID, $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
