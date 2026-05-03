<?php

namespace Tests\Feature\Api;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // REGISTER
    // =========================================================================

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => [
                         'user' => ['id', 'name', 'email'],
                         'access_token',
                         'refresh_token',
                         'token_type',
                         'expires_in',
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
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => [
                         'user' => ['id', 'name', 'email'],
                         'access_token',
                         'refresh_token',
                         'token_type',
                         'expires_in',
                     ],
                     'meta' => ['timestamp'],
                 ])
                 ->assertJson(['success' => true]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct_password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success' => false])
                 ->assertJsonMissingPath('errors');
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
            'token'      => 'valid_token_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'valid_token_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['access_token', 'refresh_token', 'token_type'],
                     'meta' => ['timestamp'],
                 ])
                 ->assertJson(['success' => true]);

        $this->assertNotNull(
            RefreshToken::where('token', 'valid_token_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                        ->first()
                        ->revoked_at
        );
    }

    public function test_refresh_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'this_token_does_not_exist_in_the_database',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success' => false])
                 ->assertJsonMissingPath('errors');
    }

    public function test_refresh_fails_with_expired_token(): void
    {
        $user = User::factory()->create();
        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => 'expired_token_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'expired_token_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_refresh_fails_with_revoked_token(): void
    {
        $user = User::factory()->create();
        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => 'revoked_token_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'expires_at' => now()->addDays(30),
            'revoked_at' => now()->subHour(),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'revoked_token_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_refresh_fails_with_missing_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', []);

        $response->assertStatus(422);
    }

    // =========================================================================
    // LOGOUT
    // =========================================================================

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/auth/logout');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'message', 'meta' => ['timestamp']])
                 ->assertJson(['success' => true]);

        $this->assertCount(0, $user->fresh()->tokens);
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
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['id', 'name', 'email', 'created_at'],
                     'meta' => ['timestamp'],
                 ])
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'id'    => $user->id,
                         'name'  => $user->name,
                         'email' => $user->email,
                     ],
                 ]);
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }
}
