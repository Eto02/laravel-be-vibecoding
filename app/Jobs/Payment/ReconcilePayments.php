<?php

namespace App\Jobs\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcilePayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PaymentService $paymentService): void
    {
        $graceConfig  = config('payment.expiry_grace_minutes', []);
        $defaultGrace = 30;
        $batchSize    = config('payment.reconcile_batch_size', 50);

        // Query payments that are past expiry but still within the per-gateway grace window.
        // expireUnpaidPayments() only fires AFTER grace ends; this job catches the gap before that.
        $candidates = Payment::where('status', PaymentStatus::Pending)
            ->where(function ($query) use ($graceConfig, $defaultGrace) {
                foreach ($graceConfig as $gateway => $graceMinutes) {
                    $query->orWhere(function ($q) use ($gateway, $graceMinutes) {
                        $q->where('gateway', $gateway)
                          ->where('expires_at', '<', now())
                          ->where('expires_at', '>=', now()->subMinutes($graceMinutes));
                    });
                }

                $knownGateways = array_keys($graceConfig);
                if (! empty($knownGateways)) {
                    $query->orWhere(function ($q) use ($knownGateways, $defaultGrace) {
                        $q->whereNotIn('gateway', $knownGateways)
                          ->where('expires_at', '<', now())
                          ->where('expires_at', '>=', now()->subMinutes($defaultGrace));
                    });
                }
            })
            ->limit($batchSize)
            ->get();

        foreach ($candidates as $payment) {
            try {
                $paymentService->handleApiStatusUpdate($payment);
            } catch (\Throwable $e) {
                Log::warning('ReconcilePayments: failed to update payment status', [
                    'payment_id' => $payment->id,
                    'gateway'    => $payment->gateway,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
