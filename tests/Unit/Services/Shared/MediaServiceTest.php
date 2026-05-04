<?php

namespace Tests\Unit\Services\Shared;

use App\Services\Shared\MediaService;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(RedisFactory::class)->connection()->flushdb();
    }

    private function makeService(FilesystemAdapter $disk): MediaService
    {
        $storageMock = $this->createMock(FilesystemManager::class);
        $storageMock->method('disk')->willReturn($disk);

        return new MediaService($storageMock, $this->app->make(RedisFactory::class));
    }

    public function test_generate_presigned_url_stores_session_in_redis(): void
    {
        $diskMock = $this->createMock(FilesystemAdapter::class);
        $diskMock->method('temporaryUploadUrl')->willReturn('https://r2.example.com/upload');

        $service = $this->makeService($diskMock);
        $result  = $service->generatePresignedUrl('products', 'photo.jpg', 'image/jpeg');

        $this->assertArrayHasKey('upload_url', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('public_url', $result);
        $this->assertStringStartsWith('products/', $result['key']);

        $redis   = $this->app->make(RedisFactory::class);
        $session = $redis->connection()->get("media:session:{$result['key']}");
        $this->assertNotNull($session);
    }

    public function test_confirm_upload_removes_session_and_returns_true(): void
    {
        $diskMock = $this->createMock(FilesystemAdapter::class);
        $diskMock->method('exists')->willReturn(true);

        $redis = $this->app->make(RedisFactory::class);
        $redis->connection()->setex('media:session:products/test.jpg', 900, json_encode(['key' => 'products/test.jpg']));

        $service = $this->makeService($diskMock);
        $result  = $service->confirmUpload('products/test.jpg');

        $this->assertTrue($result);
        $this->assertNull($redis->connection()->get('media:session:products/test.jpg'));
    }

    public function test_confirm_upload_returns_false_when_file_missing(): void
    {
        $diskMock = $this->createMock(FilesystemAdapter::class);
        $diskMock->method('exists')->willReturn(false);

        $service = $this->makeService($diskMock);
        $this->assertFalse($service->confirmUpload('products/missing.jpg'));
    }

    public function test_cleanup_orphans_deletes_unconfirmed_files(): void
    {
        $diskMock = $this->createMock(FilesystemAdapter::class);
        $diskMock->expects($this->once())->method('exists')->willReturn(true);
        $diskMock->expects($this->once())->method('delete');

        $redis = $this->app->make(RedisFactory::class);
        $redis->connection()->setex(
            'media:session:products/orphan.jpg',
            900,
            json_encode(['key' => 'products/orphan.jpg', 'confirmed' => false]),
        );

        $service = $this->makeService($diskMock);
        $deleted = $service->cleanupOrphans();

        $this->assertSame(1, $deleted);
    }
}
