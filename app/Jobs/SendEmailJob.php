<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $to,
        private readonly Mailable|null $mailable = null,
        private readonly string|null $subject = null,
        private readonly string|null $body = null,
    ) {}

    public static function dispatchRaw(string $to, string $subject, string $body): void
    {
        static::dispatch($to, null, $subject, $body);
    }

    public function handle(): void
    {
        if ($this->mailable !== null) {
            Mail::to($this->to)->send($this->mailable);
            return;
        }

        Mail::raw($this->body ?? '', function ($message) {
            $message->to($this->to)->subject($this->subject ?? '');
        });
    }
}
