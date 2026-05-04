<?php

namespace App\Contracts\Shared;

interface MediaServiceInterface
{
    /** @return array{upload_url: string, key: string, public_url: string} */
    public function generatePresignedUrl(
        string $folder,
        string $filename,
        string $mimeType,
        int $expiresInSeconds = 300,
    ): array;

    public function confirmUpload(string $key): bool;

    public function delete(string $key): bool;

    public function temporaryUrl(string $key, int $expiresInSeconds = 3600): string;

    public function publicUrl(string $key): string;

    public function cleanupOrphans(): int;
}
