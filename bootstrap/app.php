<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\LogApiRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return \App\Http\Responses\ApiResponse::error('Resource not found.', 404);
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e) {
            return \App\Http\Responses\ApiResponse::error('Forbidden.', 403);
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
            return \App\Http\Responses\ApiResponse::validationError(
                'The given data was invalid.',
                $e->errors(),
            );
        });
    })->create();
