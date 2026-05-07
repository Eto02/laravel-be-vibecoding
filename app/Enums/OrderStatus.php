<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending    = 'pending';
    case Paid       = 'paid';
    case Processing = 'processing';
    case Shipped    = 'shipped';
    case Delivered  = 'delivered';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';
    case Disputed   = 'disputed';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Pending    => in_array($new, [self::Paid, self::Cancelled]),
            self::Paid       => in_array($new, [self::Processing, self::Cancelled]),
            self::Processing => in_array($new, [self::Shipped]),
            self::Shipped    => in_array($new, [self::Delivered]),
            self::Delivered  => in_array($new, [self::Completed]),
            default          => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Menunggu Pembayaran',
            self::Paid       => 'Dibayar',
            self::Processing => 'Diproses',
            self::Shipped    => 'Dikirim',
            self::Delivered  => 'Terkirim',
            self::Completed  => 'Selesai',
            self::Cancelled  => 'Dibatalkan',
            self::Disputed   => 'Sengketa',
        };
    }
}
