<?php

namespace App\Services\User;

use App\Contracts\Shared\OtpServiceInterface;
use App\Contracts\Shared\SmsServiceInterface;
use App\Exceptions\User\PhoneAlreadyTakenException;
use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Http\Request;

class UserService
{
    public function __construct(
        private readonly OtpServiceInterface $otp,
        private readonly SmsServiceInterface $sms,
    ) {}

    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function updateAvatar(User $user, string $avatarKey): User
    {
        $user->update(['avatar' => $avatarKey]);

        return $user->fresh();
    }

    public function sendPhoneOtp(User $user, string $phone, Request $request): void
    {
        $normalized = $this->normalizePhone($phone);

        $taken = User::where('phone', $normalized)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw new PhoneAlreadyTakenException($normalized);
        }

        $otp = $this->otp->generate("phone:{$normalized}");

        PhoneVerification::create([
            'user_id'    => $user->id,
            'phone'      => $normalized,
            'otp_hash'   => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(5),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        $this->sms->sendOtp($normalized, $otp);
    }

    public function verifyPhone(User $user, string $phone, string $otp): void
    {
        $normalized = $this->normalizePhone($phone);

        $this->otp->verify("phone:{$normalized}", $otp);

        $user->phone             = $normalized;
        $user->phone_verified_at = now();
        $user->save();

        PhoneVerification::where('user_id', $user->id)
            ->where('phone', $normalized)
            ->whereNull('verified_at')
            ->latest('created_at')
            ->limit(1)
            ->update(['verified_at' => now()]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return '+' . ltrim($digits, '+');
    }
}
