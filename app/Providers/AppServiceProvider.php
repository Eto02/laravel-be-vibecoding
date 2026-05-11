<?php

namespace App\Providers;

use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\EmailServiceInterface;
use App\Contracts\Shared\IdempotencyServiceInterface;
use App\Contracts\Shared\MediaServiceInterface;
use App\Contracts\Shared\OtpServiceInterface;
use App\Contracts\Shared\SmsServiceInterface;
use App\Models\ProductVariant;
use App\Observers\ProductVariantObserver;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\XenditPaymentService;
use App\Services\Shared\CacheService;
use App\Services\Shared\EmailService;
use App\Services\Shared\IdempotencyService;
use App\Services\Shared\MediaService;
use App\Services\Shared\OtpService;
use App\Services\Shared\SmsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Payment
        $this->app->bind(PaymentGatewayInterface::class, XenditPaymentService::class);

        // Shared Services
        $this->app->bind(CacheServiceInterface::class, CacheService::class);
        $this->app->bind(EmailServiceInterface::class, EmailService::class);
        $this->app->bind(OtpServiceInterface::class, OtpService::class);
        $this->app->bind(MediaServiceInterface::class, MediaService::class);
        $this->app->bind(IdempotencyServiceInterface::class, IdempotencyService::class);
        $this->app->bind(SmsServiceInterface::class, SmsService::class);
    }

    public function boot(): void
    {
        ProductVariant::observe(ProductVariantObserver::class);
    }
}
