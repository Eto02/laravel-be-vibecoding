<?php

namespace App\Services\Auth;

use App\Events\Auth\UserRegistered;
use App\Mail\Auth\PasswordResetMail;
use App\Models\RefreshToken;
use App\Models\User;
use App\Contracts\Shared\EmailServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly EmailServiceInterface $email,
    ) {}

    public function registerUser(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        event(new UserRegistered($user));

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
        // TransientToken (used in tests via actingAs) has no delete() — guard against it
        $token = $user->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        } else {
            $user->tokens()->delete();
        }

        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

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

    public function verifyEmail(int $id, string $hash): bool
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->email), $hash)) {
            return false;
        }

        if ($user->email_verified_at !== null) {
            return true;
        }

        $user->email_verified_at = now();
        $user->save();

        return true;
    }

    public function resendVerificationEmail(User $user): void
    {
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['Email is already verified.'],
            ]);
        }

        $this->email->send($user, new \App\Mail\Auth\EmailVerificationMail($user));
    }

    public function forgotPassword(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Silently succeed to prevent email enumeration
            return;
        }

        $token = Password::broker()->createToken($user);
        $this->email->send($user, new PasswordResetMail($user, $token));
    }

    public function resetPassword(array $data): bool
    {
        $status = Password::broker()->reset(
            [
                'email'                 => $data['email'],
                'password'              => $data['password'],
                'password_confirmation' => $data['password'],
                'token'                 => $data['token'],
            ],
            function (User $user, string $password) {
                $user->update(['password' => $password]);
                // Revoke all existing tokens after password reset
                $user->tokens()->delete();
                $user->refreshTokens()->update(['revoked_at' => now()]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }

        return true;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $newPassword]);

        // Revoke all other tokens except the current session
        $currentToken   = $user->currentAccessToken();
        $currentTokenId = $currentToken instanceof \Laravel\Sanctum\PersonalAccessToken ? $currentToken->id : null;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return true;
    }

    public function getSessions(User $user): array
    {
        return $user->refreshTokens()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (RefreshToken $token) => [
                'id'         => $token->id,
                'created_at' => $token->created_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
            ])
            ->all();
    }

    public function revokeSession(User $user, int $sessionId): bool
    {
        $refreshToken = $user->refreshTokens()->findOrFail($sessionId);
        $refreshToken->update(['revoked_at' => now()]);

        return true;
    }

    public function issueToken(User $user, string $provider = 'password'): array
    {
        $accessToken     = $user->createToken('access_token')->plainTextToken;
        $refreshTokenStr = Str::random(64);

        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => $refreshTokenStr,
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'user' => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'avatar'             => $user->avatar,
                'email_verified_at'  => $user->email_verified_at?->toISOString(),
            ],
            'access_token'  => $accessToken,
            'refresh_token' => $refreshTokenStr,
            'token_type'    => 'Bearer',
            'expires_in'    => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : 3600,
            'provider'      => $provider,
        ];
    }
}
