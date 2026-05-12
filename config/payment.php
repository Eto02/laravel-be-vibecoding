<?php

return [
    'default_gateway'  => env('PAYMENT_GATEWAY', 'xendit'),
    'expiry_minutes'   => (int) env('PAYMENT_EXPIRY_MINUTES', 15),
];
