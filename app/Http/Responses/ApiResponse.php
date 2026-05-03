<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200, array $paginationMeta = []): JsonResponse
    {
        $meta = ['timestamp' => now()->toISOString()];

        if (!empty($paginationMeta)) {
            $meta['pagination'] = $paginationMeta;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta,
        ], $status);
    }

    public static function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'meta'    => ['timestamp' => now()->toISOString()],
        ], $status);
    }

    public static function validationError(string $message, array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'meta'    => ['timestamp' => now()->toISOString()],
        ], 422);
    }
}
