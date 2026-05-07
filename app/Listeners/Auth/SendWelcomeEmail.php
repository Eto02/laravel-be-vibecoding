<?php

namespace App\Listeners\Auth;

use App\Contracts\Shared\EmailServiceInterface;
use App\Events\Auth\UserRegistered;
use App\Mail\Auth\WelcomeMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeEmail implements ShouldQueue
{
    public function __construct(
        private readonly EmailServiceInterface $email,
    ) {}

    public function handle(UserRegistered $event): void
    {
        $this->email->send($event->user, new WelcomeMail($event->user));
    }
}
