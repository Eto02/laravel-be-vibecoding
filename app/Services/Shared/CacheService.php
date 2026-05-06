<?php

namespace App\Services\Shared;

use App\Contracts\Shared\CacheServiceInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class CacheService implements CacheServiceInterface
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return $this->cache->remember($key, $ttl, $callback);
    }

    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }
}
