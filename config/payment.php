<?php

return [
    'default_gateway'  => env('PAYMENT_GATEWAY', 'xendit'),
    'expiry_minutes'   => (int) env('PAYMENT_EXPIRY_MINUTES', 15),

    /*
     * Scheduler safety-net grace period per gateway (minutes).
     *
     * Xendit  → webhook arrives quickly; 30-min grace is enough.
     * Midtrans → VA payments at the bank can stay payable for up to 24 hours
     *            regardless of the Snap session expiry we set. We rely on the
     *            settlement/expire webhook as the source of truth. The scheduler
     *            only fires as an absolute last resort after this grace period.
     */
    'expiry_grace_minutes' => [
        'xendit'   => (int) env('PAYMENT_EXPIRY_GRACE_XENDIT', 30),
        'midtrans' => (int) env('PAYMENT_EXPIRY_GRACE_MIDTRANS', 1440), // 24 hours
    ],

    /*
     * Timeout (seconds) for the dual-verification gateway API call inside ProcessWebhookJob
     * and ReconcilePayments. Keep low — a timeout causes the job to retry with backoff.
     */
    'dual_verification_timeout_seconds' => (int) env('PAYMENT_DUAL_VERIFICATION_TIMEOUT', 5),

    /*
     * Max payments per ReconcilePayments run. Keeps each job fast enough to finish
     * well within the 5-minute schedule interval.
     */
    'reconcile_batch_size' => (int) env('PAYMENT_RECONCILE_BATCH_SIZE', 50),
];
