<?php

return [
    'secret_key'    => env('XENDIT_SECRET_KEY', ''),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN', ''),
    'base_url'      => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    'webhook_url'   => env('XENDIT_WEBHOOK_URL', env('APP_URL', 'http://localhost') . '/api/webhooks/xendit'),
];
