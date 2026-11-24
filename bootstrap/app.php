<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Http\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetLocaleFromAcceptLanguage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global, before routing: an unknown route or a wrong method still gets a request id,
        // and under /api/* a JSON answer in the client's language.
        $middleware->prepend([
            AssignRequestId::class,
            ForceJsonResponse::class,
            SetLocaleFromAcceptLanguage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The client can act on these; they are not bugs to track.
        $exceptions->dontReport(DomainException::class);

        $exceptions->render(
            fn (Throwable $exception, Request $request): ?JsonResponse => new ApiExceptionRenderer()->render($exception, $request),
        );
    })->create();
