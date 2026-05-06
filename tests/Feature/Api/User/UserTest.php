<?php

namespace Tests\Feature\Api\User;

use App\Contracts\Shared\MediaServiceInterface;
use App\Contracts\Shared\OtpServiceInterface;
use App\Contracts\Shared\SmsServiceInterface;
use App\Exceptions\Otp\OtpExpiredException;
use App\Exceptions\Otp\OtpRateLimitException;
use App\Exceptions\User\PhoneAlreadyTakenException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    // ── GET /users/me ──────────────────────────────────────────────────────────

    public function test_show_profile_requires_authentication(): void
    {
        $this->getJson('/api/users/me')->assertStatus(401);
    }

    public function test_show_profile_returns_full_profile(): void
    {
        $user = User::factory()->create(['bio' => 'Hello world']);

        $this->actingAs($user)->getJson('/api/users/me')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['id', 'name', 'email', 'email_verified_at', 'phone', 'phone_verified_at', 'avatar_url', 'bio', 'created_at'],
            ])
            ->assertJson(['success' => true, 'data' => ['bio' => 'Hello world']]);
    }

    // ── PUT /users/me ──────────────────────────────────────────────────────────

    public function test_update_profile_updates_name_and_bio(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->putJson('/api/users/me', ['name' => 'New Name', 'bio' => 'Updated bio'])
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['name' => 'New Name', 'bio' => 'Updated bio']]);
    }

    public function test_update_profile_ignores_phone_field(): void
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)->putJson('/api/users/me', [
            'name'  => 'Test',
            'phone' => '+6281234567890',
        ]);

        // phone is not an accepted field in UpdateProfileRequest — returns 422
        $response->assertStatus(422);
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->putJson('/api/users/me', ['name' => 'X'])->assertStatus(401);
    }

    // ── POST /users/me/avatar ──────────────────────────────────────────────────

    public function test_upload_avatar_generates_presigned_url(): void
    {
        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->with('avatars', 'photo.jpg', 'image/jpeg')
                ->andReturn([
                    'upload_url' => 'https://r2.example.com/upload',
                    'key'        => 'avatars/uuid.jpg',
                    'public_url' => 'https://pub.r2.dev/avatars/uuid.jpg',
                ]);
        });

        $this->actingAs($this->actingUser())
            ->postJson('/api/users/me/avatar', ['filename' => 'photo.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['upload_url', 'key', 'public_url']]);
    }

    public function test_upload_avatar_rejects_non_image_mime(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/users/me/avatar', ['filename' => 'file.pdf', 'mime' => 'application/pdf'])
            ->assertStatus(422);
    }

    // ── POST /users/me/avatar/confirm ──────────────────────────────────────────

    public function test_confirm_avatar_updates_user_avatar(): void
    {
        $user = $this->actingUser();

        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('confirmUpload')->once()->with('avatars/uuid.jpg')->andReturn(true);
        });

        $this->actingAs($user)
            ->postJson('/api/users/me/avatar/confirm', ['key' => 'avatars/uuid.jpg'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'avatar' => 'avatars/uuid.jpg']);
    }

    public function test_confirm_avatar_returns_422_when_file_missing(): void
    {
        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('confirmUpload')->once()->andReturn(false);
        });

        $this->actingAs($this->actingUser())
            ->postJson('/api/users/me/avatar/confirm', ['key' => 'avatars/missing.jpg'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ── POST /users/phone/send-otp ─────────────────────────────────────────────

    public function test_send_phone_otp_succeeds(): void
    {
        $this->mock(OtpServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn('123456');
        });
        $this->mock(SmsServiceInterface::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->once();
        });

        $this->actingAs($this->actingUser())
            ->postJson('/api/users/phone/send-otp', ['phone' => '081234567890'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_send_phone_otp_fails_when_phone_taken_by_other_user(): void
    {
        User::factory()->create(['phone' => '+6281234567890']);

        $this->mock(OtpServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generate')->never();
        });
        $this->mock(SmsServiceInterface::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->never();
        });

        $this->actingAs($this->actingUser())
            ->postJson('/api/users/phone/send-otp', ['phone' => '+6281234567890'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_send_phone_otp_returns_429_on_rate_limit(): void
    {
        $this->mock(OtpServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andThrow(new OtpRateLimitException('phone:+6281234567890'));
        });
        $this->mock(SmsServiceInterface::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->never();
        });

        $this->actingAs($this->actingUser())
            ->postJson('/api/users/phone/send-otp', ['phone' => '+6281234567890'])
            ->assertStatus(429);
    }

    public function test_send_otp_requires_authentication(): void
    {
        $this->postJson('/api/users/phone/send-otp', ['phone' => '+6281234567890'])->assertStatus(401);
    }

    // ── POST /users/phone/verify ───────────────────────────────────────────────

    public function test_verify_phone_marks_user_verified(): void
    {
        $user = $this->actingUser();

        $this->mock(OtpServiceInterface::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });
        $this->mock(SmsServiceInterface::class, function ($mock) {}); // not called

        $this->actingAs($user)
            ->postJson('/api/users/phone/verify', ['phone' => '+6281234567890', 'otp' => '123456'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'phone' => '+6281234567890',
        ]);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_verify_phone_returns_422_on_expired_otp(): void
    {
        $this->mock(OtpServiceInterface::class, function ($mock) {
            $mock->shouldReceive('verify')->once()->andThrow(new OtpExpiredException('phone:+6281234567890'));
        });
        $this->mock(SmsServiceInterface::class, function ($mock) {});

        $this->actingAs($this->actingUser())
            ->postJson('/api/users/phone/verify', ['phone' => '+6281234567890', 'otp' => '000000'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
