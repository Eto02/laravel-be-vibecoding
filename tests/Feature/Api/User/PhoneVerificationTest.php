<?php

namespace Tests\Feature\Api\User;

use App\Contracts\Shared\OtpServiceInterface;
use App\Contracts\Shared\SmsServiceInterface;
use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ── Audit trail ────────────────────────────────────────────────────────────

    public function test_send_otp_creates_audit_record(): void
    {
        $user = User::factory()->create();

        $this->mock(OtpServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn('123456');
        });
        $this->mock(SmsServiceInterface::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->once();
        });

        $this->actingAs($user)
            ->postJson('/api/users/phone/send-otp', ['phone' => '+6281234567890']);

        $this->assertDatabaseHas('phone_verifications', [
            'user_id' => $user->id,
            'phone'   => '+6281234567890',
        ]);

        $record = PhoneVerification::where('user_id', $user->id)->first();
        $this->assertNotNull($record->otp_hash);
        $this->assertNotNull($record->ip_address);
        $this->assertNull($record->verified_at);
    }

    public function test_verify_phone_marks_audit_record_as_verified(): void
    {
        $user = User::factory()->create();

        $this->mock(OtpServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn('654321');
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });
        $this->mock(SmsServiceInterface::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->once();
        });

        $this->actingAs($user)
            ->postJson('/api/users/phone/send-otp', ['phone' => '+6282345678901']);

        $this->actingAs($user)
            ->postJson('/api/users/phone/verify', ['phone' => '+6282345678901', 'otp' => '654321']);

        $record = PhoneVerification::where('user_id', $user->id)->first();
        $this->assertNotNull($record->verified_at);
    }

    // ── Phone normalization ────────────────────────────────────────────────────

    public function test_phone_is_normalized_to_e164(): void
    {
        $user = User::factory()->create();

        $this->mock(OtpServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn('123456');
        });
        $this->mock(SmsServiceInterface::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->once();
        });

        // Send with local format 08xxx
        $this->actingAs($user)
            ->postJson('/api/users/phone/send-otp', ['phone' => '081234567890']);

        $this->assertDatabaseHas('phone_verifications', [
            'user_id' => $user->id,
            'phone'   => '+6281234567890',
        ]);
    }
}
