<?php

namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function registerUser(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // Automatically hashed by model cast in Laravel 11
        ]);

        return $this->generateTokenPayload($user);
    }

    public function loginUser(array $credentials)
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        return $this->generateTokenPayload($user);
    }

    public function logoutUser(User $user)
    {
        // Revoke all tokens for the user
        $user->tokens()->delete();
        $user->refreshTokens()->update(['revoked_at' => now()]);

        return true;
    }

    public function refreshToken(string $token)
    {
        $refreshToken = RefreshToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->first();

        if (!$refreshToken) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Invalid or expired refresh token.'],
            ]);
        }

        $user = $refreshToken->user;

        // Revoke the old refresh token (Rotation)
        $refreshToken->update(['revoked_at' => now()]);

        return $this->generateTokenPayload($user);
    }

    protected function generateTokenPayload(User $user)
    {
        // Create Sanctum Access Token
        $accessToken = $user->createToken('access_token')->plainTextToken;

        // Create Refresh Token
        $refreshTokenStr = Str::random(64);
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => $refreshTokenStr,
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshTokenStr,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : 3600,
        ];
    }
}
