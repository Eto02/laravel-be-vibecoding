<?php

namespace App\Listeners\Auth;

use App\Contracts\Shared\EmailServiceInterface;
use App\Events\Auth\UserRegistered;
use App\Mail\Auth\EmailVerificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailVerificationNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly EmailServiceInterface $email,
    ) {}

    public function handle(UserRegistered $event): void
    {
        $this->email->send($event->user, new EmailVerificationMail($event->user));
    }
}
