<?php

namespace App\Services\Shared;

use App\Contracts\Shared\EmailServiceInterface;
use App\Jobs\SendEmailJob;
use App\Models\User;
use Illuminate\Mail\Mailable;

class EmailService implements EmailServiceInterface
{
    public function send(User $user, Mailable $mail): void
    {
        SendEmailJob::dispatch($user->email, $mail);
    }

    public function sendRaw(string $to, string $subject, string $body): void
    {
        SendEmailJob::dispatchRaw($to, $subject, $body);
    }
}
