<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->logRequest($request, $response, $duration, $requestId);

        return $response;
    }

    protected function logRequest(Request $request, Response $response, float $duration, string $requestId): void
    {
        try {
            if ($request->is('health') || $request->is('up')) {
                return;
            }

            $logData = [
                'request_id'  => $requestId,
                'timestamp'   => now()->toIso8601String(),
                'method'      => $request->method(),
                'url'         => $request->fullUrl(),
                'path'        => $request->path(),
                'ip_address'  => $request->ip(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $duration,
                'user_id'     => $request->user()?->id,
                'user_agent'  => $request->userAgent(),
                'payload'     => $this->maskSensitiveData($request->all()),
            ];

            if ($response->getStatusCode() >= 400) {
                $logData['response_content'] = json_decode($response->getContent(), true);
            }

            // 1. Log to File (JSON for Grafana/Loki)
            Log::info('API Request Log', $logData);

            // 2. Log to Database (Async for Audit)
            \App\Jobs\ProcessApiLog::dispatch($logData);
        } catch (\Throwable $e) {
            // Log the error to the default emergency logger but don't break the request
            Log::error('Logging Middleware Error: ' . $e->getMessage());
        }
    }

    protected function maskSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'refresh_token', 'access_token', 'client_secret'];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }
}
