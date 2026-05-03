<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'external_id',
        'amount',
        'status',
        'invoice_url',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status'  => TransactionStatus::class,
            'amount'  => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
