<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OAuthService;
use Illuminate\Http\Request;

class OAuthController extends Controller
{
    protected $oauthService;

    public function __construct(OAuthService $oauthService)
    {
        $this->oauthService = $oauthService;
    }

    public function redirect(string $provider)
    {
        return $this->oauthService->redirectToProvider($provider);
    }

    public function callback(string $provider)
    {
        try {
            $result = $this->oauthService->handleCallback($provider);

            return response()->json([
                'status' => 'success',
                'message' => 'Authenticated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'OAuth authentication failed',
                'errors' => [
                    'oauth' => [$e->getMessage()]
                ]
            ], 401);
        }
    }
}
