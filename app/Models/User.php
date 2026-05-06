<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'avatar', 'phone', 'phone_verified_at', 'bio'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function oauthAccounts()
    {
        return $this->hasMany(OAuthAccount::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function phoneVerifications()
    {
        return $this->hasMany(PhoneVerification::class);
    }

    public function store()
    {
        return $this->hasOne(Store::class);
    }
}
