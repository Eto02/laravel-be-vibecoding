<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\ApiLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_request_is_logged_in_json_and_database(): void
    {
        $user = User::factory()->create();

        // Expectation for JSON file logging
        Log::shouldReceive('info')
            ->once();

        $this->actingAs($user)->getJson('/api/auth/me');

        // Check if database log exists (even if it's dispatched via job, in tests it runs synchronously)
        $this->assertDatabaseHas('api_logs', [
            'path' => 'api/auth/me',
            'user_id' => $user->id,
            'status_code' => 200
        ]);
    }

    public function test_sensitive_data_is_masked(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'secret123'
        ]);

        $this->assertDatabaseHas('api_logs', [
            'path' => 'api/auth/login',
            'payload' => json_encode(['email' => 'test@example.com', 'password' => '********'])
        ]);
    }
}
