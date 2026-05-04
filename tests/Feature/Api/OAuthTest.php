<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_can_authenticate_via_oauth_callback()
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('123456');
        $abstractUser->shouldReceive('getEmail')->andReturn('oauth@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('OAuth User');
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://avatar.url');
        $abstractUser->token = 'fake_token';
        $abstractUser->refreshToken = 'fake_refresh_token';

        $provider = \Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/callback');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'refresh_token',
                    'provider',
                ],
                'meta' => ['timestamp'],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'oauth@example.com']);
        $this->assertDatabaseHas('oauth_accounts', [
            'provider' => 'google',
            'provider_user_id' => '123456'
        ]);
    }

    public function test_oauth_links_to_existing_user_by_email()
    {
        $user = \App\Models\User::factory()->create(['email' => 'existing@example.com']);

        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('987654');
        $abstractUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('OAuth User');
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://avatar.url');
        $abstractUser->token = 'fake_token';
        $abstractUser->refreshToken = 'fake_refresh_token';

        $provider = \Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $response = $this->getJson('/api/auth/github/callback');

        $response->assertStatus(200);
        
        $this->assertEquals(1, \App\Models\User::count());
        $this->assertDatabaseHas('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => 'github'
        ]);
    }
}
