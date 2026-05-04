<?php

namespace App\Contracts\Shared;

interface IdempotencyServiceInterface
{
    public function check(string $key, callable $callback, int $ttl = 86400): mixed;
}
