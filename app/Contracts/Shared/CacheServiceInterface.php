<?php

namespace App\Contracts\Shared;

interface CacheServiceInterface
{
    public function remember(string $key, int $ttl, callable $callback): mixed;

    public function forget(string $key): void;

    public function has(string $key): bool;
}
