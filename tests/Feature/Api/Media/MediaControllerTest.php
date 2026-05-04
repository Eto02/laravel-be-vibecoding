<?php

namespace Tests\Feature\Api\Media;

use App\Contracts\Shared\MediaServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_presigned_url_requires_authentication(): void
    {
        $response = $this->postJson('/api/media/presigned-url', [
            'folder'   => 'products',
            'filename' => 'photo.jpg',
            'mime'     => 'image/jpeg',
        ]);

        $response->assertStatus(401);
    }

    public function test_presigned_url_returns_upload_info(): void
    {
        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->with('products', 'photo.jpg', 'image/jpeg')
                ->andReturn([
                    'upload_url' => 'https://r2.example.com/upload',
                    'key'        => 'products/uuid.jpg',
                    'public_url' => 'https://pub.r2.dev/products/uuid.jpg',
                ]);
        });

        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/media/presigned-url', [
                'folder'   => 'products',
                'filename' => 'photo.jpg',
                'mime'     => 'image/jpeg',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['upload_url', 'key', 'public_url'],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_presigned_url_validates_folder(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/media/presigned-url', [
                'folder'   => 'invalid-folder',
                'filename' => 'photo.jpg',
                'mime'     => 'image/jpeg',
            ]);

        $response->assertStatus(422);
    }

    public function test_confirm_upload_returns_public_url(): void
    {
        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('confirmUpload')
                ->once()
                ->with('products/uuid.jpg')
                ->andReturn(true);
            $mock->shouldReceive('publicUrl')
                ->once()
                ->with('products/uuid.jpg')
                ->andReturn('https://pub.r2.dev/products/uuid.jpg');
        });

        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/media/confirm', ['key' => 'products/uuid.jpg']);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['public_url']])
            ->assertJson(['success' => true]);
    }

    public function test_confirm_upload_returns_422_when_file_missing(): void
    {
        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('confirmUpload')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/media/confirm', ['key' => 'products/missing.jpg']);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_delete_file_successfully(): void
    {
        $this->mock(MediaServiceInterface::class, function ($mock) {
            $mock->shouldReceive('delete')->once()->with('products/uuid.jpg')->andReturn(true);
        });

        $response = $this->actingAs($this->actingUser())
            ->deleteJson('/api/media', ['key' => 'products/uuid.jpg']);

        $response->assertStatus(204);
    }
}
