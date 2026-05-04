<?php

namespace App\Console\Commands\Media;

use App\Contracts\Shared\MediaServiceInterface;
use Illuminate\Console\Command;

class CleanupOrphansCommand extends Command
{
    protected $signature   = 'media:cleanup-orphans';
    protected $description = 'Delete R2 files that were presigned but never confirmed within the session TTL.';

    public function handle(MediaServiceInterface $media): int
    {
        $deleted = $media->cleanupOrphans();
        $this->info("Orphan cleanup complete. Deleted {$deleted} file(s).");
        return self::SUCCESS;
    }
}
