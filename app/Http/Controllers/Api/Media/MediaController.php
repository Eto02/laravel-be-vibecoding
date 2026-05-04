<?php

namespace App\Http\Controllers\Api\Media;

use App\Contracts\Shared\MediaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\ConfirmUploadRequest;
use App\Http\Requests\Media\GeneratePresignedUrlRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaServiceInterface $media,
    ) {}

    public function presignedUrl(GeneratePresignedUrlRequest $request): JsonResponse
    {
        $result = $this->media->generatePresignedUrl(
            $request->input('folder'),
            $request->input('filename'),
            $request->input('mime'),
        );

        return ApiResponse::success('Presigned URL generated.', $result, 201);
    }

    public function confirm(ConfirmUploadRequest $request): JsonResponse
    {
        $key       = $request->input('key');
        $confirmed = $this->media->confirmUpload($key);

        if (! $confirmed) {
            return ApiResponse::error('File not found in storage. Upload may have failed.', 422);
        }

        return ApiResponse::success('Upload confirmed.', [
            'public_url' => $this->media->publicUrl($key),
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        $request->validate(['key' => ['required', 'string', 'max:500']]);

        $this->media->delete($request->input('key'));

        return ApiResponse::success('File deleted.', null, 204);
    }
}
