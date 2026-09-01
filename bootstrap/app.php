<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Every API error response goes through this single place, so the
        // shape is consistent and we never leak stack traces / SQL errors
        // in production (APP_DEBUG=false is what actually suppresses the
        // framework's own debug page; this ensures our JSON is clean
        // regardless of that flag once it reaches the client as JSON).

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Too many requests. Please slow down and try again shortly.',
                ], 429);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! app()->environment('local', 'testing') && ($request->is('api/*') || $request->expectsJson())) {
                return response()->json([
                    'message' => 'An unexpected error occurred. Please try again later.',
                ], 500);
            }
        });
    })->create();
