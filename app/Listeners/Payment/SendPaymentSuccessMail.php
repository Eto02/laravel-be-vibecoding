<?php

namespace App\Listeners\Payment;

use App\Events\Payment\PaymentCaptured;
use App\Mail\Payment\PaymentSuccessMail;
use App\Contracts\Shared\EmailServiceInterface as EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentSuccessMail implements ShouldQueue
{
    public function __construct(
        private readonly EmailService $email,
    ) {}

    public function handle(PaymentCaptured $event): void
    {
        $payment = $event->payment;
        $user    = $payment->order?->user;

        if (! $user) {
            return;
        }

        $this->email->send($user, new PaymentSuccessMail($payment));
    }
}
