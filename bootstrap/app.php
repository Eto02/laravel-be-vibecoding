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
        $middleware->alias([
            'merchant' => \App\Http\Middleware\EnsureMerchantOwnership::class,
            'admin'    => \App\Http\Middleware\EnsureAdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel 11 maps ModelNotFoundException → NotFoundHttpException via mapException()
        // before render callbacks run, so we must handle NotFoundHttpException directly.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
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

        $exceptions->render(function (\App\Exceptions\Otp\OtpRateLimitException $e) {
            return \App\Http\Responses\ApiResponse::error('Too many OTP requests. Please try again later.', 429);
        });

        $exceptions->render(function (\App\Exceptions\Otp\OtpMaxRetryException $e) {
            return \App\Http\Responses\ApiResponse::error('OTP invalidated after too many failed attempts.', 422);
        });

        $exceptions->render(function (\App\Exceptions\Otp\OtpExpiredException $e) {
            return \App\Http\Responses\ApiResponse::error('OTP has expired. Please request a new one.', 422);
        });

        $exceptions->render(function (\App\Exceptions\User\PhoneAlreadyTakenException $e) {
            return \App\Http\Responses\ApiResponse::error('This phone number is already registered.', 422);
        });

        $exceptions->render(function (\App\Exceptions\Merchant\StoreAlreadyExistsException $e) {
            return \App\Http\Responses\ApiResponse::error('You already have a store.', 422);
        });

        $exceptions->render(function (\App\Exceptions\Merchant\KycNotAllowedException $e) {
            return \App\Http\Responses\ApiResponse::error('KYC documents cannot be uploaded at this time.', 422);
        });

        $exceptions->render(function (\App\Exceptions\Merchant\AlreadyFollowingException $e) {
            return \App\Http\Responses\ApiResponse::error('You are already following this store.', 409);
        });

        $exceptions->render(function (\DomainException $e) {
            return \App\Http\Responses\ApiResponse::error($e->getMessage(), 422);
        });
    })->create();
