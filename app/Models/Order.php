<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'store_id',
        'address_snapshot',
        'subtotal',
        'shipping_fee',
        'discount',
        'total',
        'shipping_courier',
        'shipping_service',
        'tracking_number',
        'status',
        'payment_due_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array',
            'subtotal'         => 'integer',
            'shipping_fee'     => 'integer',
            'discount'         => 'integer',
            'total'            => 'integer',
            'status'           => OrderStatus::class,
            'payment_due_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest();
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(OrderDispute::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function isOwnedByUser(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function isOwnedByStore(int $storeId): bool
    {
        return $this->store_id === $storeId;
    }
}
