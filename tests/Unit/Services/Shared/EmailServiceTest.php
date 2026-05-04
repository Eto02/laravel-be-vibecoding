<?php

namespace Tests\Unit\Services\Shared;

use App\Jobs\SendEmailJob;
use App\Models\User;
use App\Services\Shared\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(EmailService::class);
    }

    public function test_send_dispatches_send_email_job(): void
    {
        Queue::fake();

        $user     = User::factory()->create();
        $mailable = $this->createMock(Mailable::class);

        $this->service->send($user, $mailable);

        Queue::assertPushed(SendEmailJob::class);
    }

    public function test_send_raw_dispatches_send_email_job(): void
    {
        Queue::fake();

        $this->service->sendRaw('user@example.com', 'Hello', 'Body text');

        Queue::assertPushed(SendEmailJob::class);
    }
}
