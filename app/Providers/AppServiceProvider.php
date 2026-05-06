<?php

namespace App\Providers;

use App\Contracts\Shared\EmailServiceInterface;
use App\Contracts\Shared\IdempotencyServiceInterface;
use App\Contracts\Shared\MediaServiceInterface;
use App\Contracts\Shared\OtpServiceInterface;
use App\Events\Auth\UserRegistered;
use App\Listeners\Auth\SendEmailVerificationNotification;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\XenditPaymentService;
use App\Services\Shared\EmailService;
use App\Services\Shared\IdempotencyService;
use App\Services\Shared\MediaService;
use App\Services\Shared\OtpService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Payment
        $this->app->bind(PaymentGatewayInterface::class, XenditPaymentService::class);

        // Shared Services
        $this->app->bind(EmailServiceInterface::class, EmailService::class);
        $this->app->bind(OtpServiceInterface::class, OtpService::class);
        $this->app->bind(MediaServiceInterface::class, MediaService::class);
        $this->app->bind(IdempotencyServiceInterface::class, IdempotencyService::class);
    }

    public function boot(): void
    {
        Event::listen(UserRegistered::class, SendEmailVerificationNotification::class);
    }
}
