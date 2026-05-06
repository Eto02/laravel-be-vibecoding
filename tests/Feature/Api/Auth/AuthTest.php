<?php

namespace Tests\Feature\Api\Auth;

use App\Mail\Auth\EmailVerificationMail;
use App\Mail\Auth\PasswordResetMail;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // REGISTER
    // =========================================================================

    public function test_user_can_register(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'email_verified_at'],
                    'access_token', 'refresh_token', 'token_type', 'expires_in',
                ],
                'meta' => ['timestamp'],
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);
        $response->assertStatus(422);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Another User',
            'email'    => 'taken@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // LOGIN
    // =========================================================================

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['user', 'access_token', 'refresh_token', 'token_type', 'expires_in'],
                'meta' => ['timestamp'],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct_password')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);
        $response->assertStatus(422);
    }

    // =========================================================================
    // REFRESH TOKEN
    // =========================================================================

    public function test_user_can_refresh_token(): void
    {
        $user = User::factory()->create();
        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => str_repeat('a', 64),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/auth/refresh', ['refresh_token' => str_repeat('a', 64)]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNotNull(RefreshToken::where('token', str_repeat('a', 64))->first()->revoked_at);
    }

    public function test_refresh_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', ['refresh_token' => 'nonexistent_token']);
        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_refresh_fails_with_expired_token(): void
    {
        $user = User::factory()->create();
        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => str_repeat('b', 64),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/auth/refresh', ['refresh_token' => str_repeat('b', 64)]);
        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_refresh_fails_with_revoked_token(): void
    {
        $user = User::factory()->create();
        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => str_repeat('c', 64),
            'expires_at' => now()->addDays(30),
            'revoked_at' => now()->subHour(),
        ]);

        $response = $this->postJson('/api/auth/refresh', ['refresh_token' => str_repeat('c', 64)]);
        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    // =========================================================================
    // LOGOUT
    // =========================================================================

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/auth/logout');

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_logout_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }

    // =========================================================================
    // ME
    // =========================================================================

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['id' => $user->id, 'email' => $user->email]]);
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);
    }

    // =========================================================================
    // EMAIL VERIFICATION
    // =========================================================================

    public function test_email_verification_with_valid_signed_url(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'auth.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->getJson($url);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_fails_with_invalid_signature(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->getJson('/api/auth/email/verify?id='.$user->id.'&hash=fakehash&expires=9999999999&signature=bad');

        $response->assertStatus(403);
    }

    public function test_email_verification_fails_with_wrong_hash(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'auth.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'wronghash'],
        );

        $response = $this->getJson($url);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_resend_verification_email(): void
    {
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($user)->postJson('/api/auth/email/resend');

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_resend_fails_if_already_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->postJson('/api/auth/email/resend');

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    // =========================================================================
    // FORGOT / RESET PASSWORD
    // =========================================================================

    public function test_forgot_password_sends_reset_email(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_forgot_password_silently_succeeds_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'ghost@example.com']);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user  = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => 'invalid_token',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    // =========================================================================
    // CHANGE PASSWORD
    // =========================================================================

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAs($user)->putJson('/api/auth/change-password', [
            'current_password'      => 'oldpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correctpassword')]);

        $response = $this->actingAs($user)->putJson('/api/auth/change-password', [
            'current_password'      => 'wrongpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->putJson('/api/auth/change-password', [
            'current_password'      => 'old',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    // =========================================================================
    // SESSION MANAGEMENT
    // =========================================================================

    public function test_user_can_list_active_sessions(): void
    {
        $user = User::factory()->create();
        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => str_repeat('s', 64),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)->getJson('/api/auth/sessions');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => [['id', 'created_at', 'expires_at']]]);
    }

    public function test_user_can_revoke_a_session(): void
    {
        $user  = User::factory()->create();
        $token = RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => str_repeat('r', 64),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/auth/sessions/{$token->id}");

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $token = RefreshToken::create([
            'user_id'    => $other->id,
            'token'      => str_repeat('x', 64),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/auth/sessions/{$token->id}");

        $response->assertStatus(404);
    }
}
