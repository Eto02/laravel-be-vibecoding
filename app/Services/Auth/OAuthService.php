<?php

namespace App\Services\Auth;

use App\Models\OAuthAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

class OAuthService
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function redirectToProvider(string $provider): mixed
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleCallback(string $provider): array
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();
        $user       = $this->createOrLinkOAuthUser($socialUser, $provider);

        return $this->authService->issueToken($user, $provider);
    }

    private function createOrLinkOAuthUser(mixed $socialUser, string $provider): User
    {
        $oauthAccount = OAuthAccount::where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($oauthAccount) {
            $oauthAccount->update([
                'access_token'  => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at'    => property_exists($socialUser, 'expiresIn')
                    ? now()->addSeconds($socialUser->expiresIn)
                    : null,
            ]);

            return $oauthAccount->user;
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name'     => $socialUser->getName() ?? $socialUser->getNickname(),
                'email'    => $socialUser->getEmail(),
                'avatar'   => $socialUser->getAvatar(),
                'password' => null,
            ]);
        }

        OAuthAccount::create([
            'user_id'          => $user->id,
            'provider'         => $provider,
            'provider_user_id' => $socialUser->getId(),
            'access_token'     => $socialUser->token,
            'refresh_token'    => $socialUser->refreshToken,
            'expires_at'       => property_exists($socialUser, 'expiresIn')
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);

        return $user;
    }
}
