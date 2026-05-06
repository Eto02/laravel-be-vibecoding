<?php

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\MerchantStatus;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'status',
        'kyc_status',
        'city',
        'province',
        'phone',
        'rating_avg',
        'total_sales',
        'follower_count',
    ];

    protected function casts(): array
    {
        return [
            'status'     => MerchantStatus::class,
            'kyc_status' => KycStatus::class,
            'rating_avg' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StoreDocument::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(StoreFollower::class);
    }
}
