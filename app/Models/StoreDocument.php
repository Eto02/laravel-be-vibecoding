<?php

namespace App\Models;

use Database\Factories\StoreDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDocument extends Model
{
    /** @use HasFactory<StoreDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'store_id',
        'type',
        'file',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
