<?php

namespace App\Services\Shared;

use App\Contracts\Shared\MediaServiceInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;

class MediaService implements MediaServiceInterface
{
    private const SESSION_TTL = 900; // 15 minutes

    public function __construct(
        private readonly FilesystemManager $storage,
        private readonly RedisFactory $redis,
    ) {}

    public function generatePresignedUrl(
        string $folder,
        string $filename,
        string $mimeType,
        int $expiresInSeconds = 300,
    ): array {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $key       = ltrim($folder, '/').'/'.Str::uuid().'.'.$extension;

        $uploadUrl = $this->storage->disk('r2')
            ->temporaryUploadUrl($key, now()->addSeconds($expiresInSeconds), [
                'ContentType' => $mimeType,
            ]);

        $this->redis->connection()->setex(
            "media:session:{$key}",
            self::SESSION_TTL,
            json_encode([
                'key'        => $key,
                'folder'     => $folder,
                'expires_at' => now()->addSeconds(self::SESSION_TTL)->toISOString(),
                'confirmed'  => false,
            ]),
        );

        return [
            'upload_url' => $uploadUrl,
            'key'        => $key,
            'public_url' => $this->publicUrl($key),
        ];
    }

    public function confirmUpload(string $key): bool
    {
        if (! $this->storage->disk('r2')->exists($key)) {
            return false;
        }

        $this->redis->connection()->del("media:session:{$key}");

        return true;
    }

    public function delete(string $key): bool
    {
        $this->redis->connection()->del("media:session:{$key}");

        return $this->storage->disk('r2')->delete($key);
    }

    public function temporaryUrl(string $key, int $expiresInSeconds = 3600): string
    {
        return $this->storage->disk('r2')->temporaryUrl($key, now()->addSeconds($expiresInSeconds));
    }

    public function publicUrl(string $key): string
    {
        return rtrim(config('filesystems.disks.r2.url'), '/').'/'.$key;
    }

    public function cleanupOrphans(): int
    {
        // phpredis auto-prepends OPT_PREFIX on keys() search pattern AND returns keys
        // with the prefix included — strip prefix before using in get/del (which re-add it).
        $client = $this->redis->connection()->client();
        $prefix = method_exists($client, 'getOption')
            ? (string) $client->getOption(\Redis::OPT_PREFIX)
            : '';

        $rawKeys = $this->redis->connection()->keys('media:session:*');
        $deleted  = 0;

        foreach ($rawKeys as $rawKey) {
            $sessionKey = $prefix !== '' ? substr($rawKey, strlen($prefix)) : $rawKey;

            $raw = $this->redis->connection()->get($sessionKey);
            if ($raw === null) {
                continue;
            }

            $session = json_decode($raw, true);

            if (! empty($session['confirmed'])) {
                continue;
            }

            $fileKey = $session['key'] ?? str_replace('media:session:', '', $sessionKey);

            if ($this->storage->disk('r2')->exists($fileKey)) {
                $this->storage->disk('r2')->delete($fileKey);
                $deleted++;
            }

            $this->redis->connection()->del($sessionKey);
        }

        return $deleted;
    }
}
