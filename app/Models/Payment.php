<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'transaction_id',
        'gateway',
        'method',
        'gateway_ref',
        'amount',
        'status',
        'payment_details',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status'          => PaymentStatus::class,
            'amount'          => 'integer',
            'payment_details' => 'array',
            'expires_at'      => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }
}
