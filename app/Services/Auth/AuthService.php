<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function registerUser(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        return $this->issueToken($user, 'password');
    }

    public function loginUser(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        return $this->issueToken($user, 'password');
    }

    public function logoutUser(User $user): bool
    {
        $user->tokens()->delete();
        $user->refreshTokens()->update(['revoked_at' => now()]);

        return true;
    }

    public function refreshToken(string $token): array
    {
        $refreshToken = RefreshToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->first();

        if (! $refreshToken) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Invalid or expired refresh token.'],
            ]);
        }

        $user = $refreshToken->user;

        $refreshToken->update(['revoked_at' => now()]);

        return $this->issueToken($user, 'refresh');
    }

    public function issueToken(User $user, string $provider = 'password'): array
    {
        $accessToken      = $user->createToken('access_token')->plainTextToken;
        $refreshTokenStr  = Str::random(64);

        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => $refreshTokenStr,
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'user'         => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'avatar' => $user->avatar,
            ],
            'access_token'  => $accessToken,
            'refresh_token' => $refreshTokenStr,
            'token_type'    => 'Bearer',
            'expires_in'    => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : 3600,
            'provider'      => $provider,
        ];
    }
}
