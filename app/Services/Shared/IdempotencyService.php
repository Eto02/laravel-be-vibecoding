<?php

namespace App\Services\Shared;

use App\Contracts\Shared\IdempotencyServiceInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class IdempotencyService implements IdempotencyServiceInterface
{
    private const LOCK_TTL = 30; // seconds

    public function __construct(
        private readonly RedisFactory $redis,
    ) {}

    public function check(string $key, callable $callback, int $ttl = 86400): mixed
    {
        $cacheKey = "idempotency:{$key}";
        $lockKey  = "idempotency:lock:{$key}";

        $cached = $this->redis->connection()->get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true);
        }

        // Acquire lock to prevent concurrent duplicate requests
        $acquired = $this->redis->connection()->set(
            $lockKey,
            '1',
            'EX', self::LOCK_TTL,
            'NX',
        );

        if (! $acquired) {
            // Another request is processing — re-check after brief moment
            usleep(100_000);
            $cached = $this->redis->connection()->get($cacheKey);
            if ($cached !== null) {
                return json_decode($cached, true);
            }
        }

        try {
            $result = $callback();
            $this->redis->connection()->setex($cacheKey, $ttl, json_encode($result));
            return $result;
        } finally {
            $this->redis->connection()->del($lockKey);
        }
    }
}
