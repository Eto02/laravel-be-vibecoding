<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'method',
        'url',
        'path',
        'payload',
        'response_content',
        'status_code',
        'duration_ms',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'payload'          => 'array',
            'response_content' => 'array',
            'duration_ms'      => 'float',
        ];
    }
}
