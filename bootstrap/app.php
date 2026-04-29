<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        $middleware->alias([
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'tenant' => \App\Http\Middleware\EnsureTenantContext::class,
            'tenant.slug' => \App\Http\Middleware\ResolveTenantFromSlug::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('super/*')) {
                return redirect()->route('login');
            }
        });

        // 429 Too Many Requests — mensaje en español para la SPA pública y
        // cualquier endpoint que espera JSON. Incluye `retry_after` para que
        // el cliente pueda mostrar una cuenta regresiva amable.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return new JsonResponse([
                    'code' => 'too_many_requests',
                    'message' => 'Demasiadas solicitudes. Espera unos segundos e intenta de nuevo.',
                    'retry_after' => $retryAfter,
                ], 429, [
                    'Retry-After' => (string) $retryAfter,
                ]);
            }
        });
    })->create();
