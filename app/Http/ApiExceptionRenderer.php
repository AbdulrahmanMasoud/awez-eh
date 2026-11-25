<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every error under /api/* in one shape: {message, code, errors}. The message is
 * translated; nothing internal (a model, an id, an exception message, a trace) reaches the
 * client, except the trace of a crash in the local environment.
 *
 * Laravel calls it after mapping exceptions: a missing model arrives as a NotFoundHttpException
 * and a refused authorization as an AccessDeniedHttpException.
 */
final class ApiExceptionRenderer
{
    private const int TRACE_FRAMES = 20;

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        // A response someone built on purpose (abort(response(...))) is left as it is.
        if (! ApiRequest::matches($request) || $exception instanceof HttpResponseException) {
            return null;
        }

        return match (true) {
            $exception instanceof DomainException => $this->error($exception->status, $exception->errorCode, $exception->translationKey, $exception->errors())
                ->withHeaders($exception->headers()),
            $exception instanceof ValidationException => $this->error($exception->status, 'validation_failed', 'errors.validation_failed', $exception->errors()),
            $exception instanceof AuthenticationException => $this->error(Response::HTTP_UNAUTHORIZED, 'unauthenticated', 'errors.unauthenticated'),
            $exception instanceof HttpExceptionInterface => $this->httpError($exception),
            default => $this->serverError($exception),
        };
    }

    private function httpError(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();

        [$code, $translationKey] = match ($status) {
            Response::HTTP_FORBIDDEN => ['forbidden', 'errors.forbidden'],
            Response::HTTP_NOT_FOUND => ['not_found', 'errors.not_found'],
            Response::HTTP_TOO_MANY_REQUESTS => ['too_many_requests', 'errors.too_many_requests'],
            Response::HTTP_INTERNAL_SERVER_ERROR => ['server_error', 'errors.server_error'],
            // 405, 503 in maintenance, ...: the code is the status's name, e.g. method_not_allowed.
            default => [Str::slug(Response::$statusTexts[$status] ?? '', '_') ?: 'http_error', 'errors.http_error'],
        };

        // Retry-After, Allow and the rate limit headers stay.
        return $this->error($status, $code, $translationKey)->withHeaders($exception->getHeaders());
    }

    private function serverError(Throwable $exception): JsonResponse
    {
        $payload = $this->payload('server_error', 'errors.server_error');

        if (App::isLocal()) {
            $payload['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
                'trace' => array_map(
                    fn (array $frame): string => ($frame['file'] ?? '[internal]').':'.($frame['line'] ?? 0),
                    array_slice($exception->getTrace(), 0, self::TRACE_FRAMES),
                ),
            ];
        }

        return new JsonResponse($payload, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $errors  Validation messages keyed by field.
     */
    private function error(int $status, string $code, string $translationKey, array $errors = []): JsonResponse
    {
        return new JsonResponse($this->payload($code, $translationKey, $errors), $status);
    }

    /**
     * @param  array<array-key, mixed>  $errors
     * @return array<string, mixed>
     */
    private function payload(string $code, string $translationKey, array $errors = []): array
    {
        $message = __($translationKey);

        return [
            'message' => is_string($message) ? $message : $translationKey,
            'code' => $code,
            // An object even when empty, so clients always find a map.
            'errors' => $errors === [] ? new stdClass : $errors,
        ];
    }
}
