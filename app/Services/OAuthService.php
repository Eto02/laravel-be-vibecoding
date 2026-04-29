<?php

namespace App\Services;

use App\Models\User;
use App\Models\OAuthAccount;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class OAuthService
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function redirectToProvider(string $provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleCallback(string $provider)
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();

        $user = $this->createOrLinkOAuthUser($socialUser, $provider);

        return $this->authService->issueToken($user, $provider);
    }

    protected function createOrLinkOAuthUser($socialUser, string $provider)
    {
        // 1. Check if OAuth Account already exists
        $oauthAccount = OAuthAccount::where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($oauthAccount) {
            // Update token if needed
            $oauthAccount->update([
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at' => property_exists($socialUser, 'expiresIn') ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            return $oauthAccount->user;
        }

        // 2. Check if user exists with the same email
        $user = User::where('email', $socialUser->getEmail())->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'password' => null, // No password for OAuth users
            ]);
        }

        // 3. Link OAuth Account
        OAuthAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $socialUser->getId(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => property_exists($socialUser, 'expiresIn') ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);

        return $user;
    }
}
