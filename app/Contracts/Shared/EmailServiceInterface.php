<?php

namespace App\Contracts\Shared;

use App\Models\User;
use Illuminate\Mail\Mailable;

interface EmailServiceInterface
{
    public function send(User $user, Mailable $mail): void;

    public function sendRaw(string $to, string $subject, string $body): void;
}
