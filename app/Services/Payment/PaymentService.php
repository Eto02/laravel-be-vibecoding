<?php

namespace App\Services\Payment;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function createInvoice(array $data): Transaction
    {
        $externalId = 'INV-' . strtoupper(Str::random(12)) . '-' . time();

        $response = $this->gateway->createPaymentIntent([
            'external_id' => $externalId,
            'amount'      => $data['amount'],
            'description' => $data['description'] ?? 'Payment',
            'currency'    => $data['currency'] ?? 'IDR',
        ]);

        return Transaction::create([
            'external_id' => $externalId,
            'amount'      => $data['amount'],
            'status'      => TransactionStatus::Pending,
            'invoice_url' => $response['invoice_url'],
        ]);
    }

    public function handleWebhook(Request $request): Transaction
    {
        if (! $this->gateway->verifyWebhook($request)) {
            throw new \RuntimeException('Invalid webhook token.', 403);
        }

        $payload    = $request->json()->all();
        $externalId = $payload['external_id'] ?? null;
        $status     = strtoupper($payload['status'] ?? '');

        $transaction = Transaction::where('external_id', $externalId)->firstOrFail();

        $newStatus = match ($status) {
            'PAID'    => TransactionStatus::Paid,
            'EXPIRED' => TransactionStatus::Expired,
            default   => $transaction->status,
        };

        $updateData = ['status' => $newStatus];

        if ($newStatus === TransactionStatus::Paid) {
            $updateData['paid_at'] = now();
        }

        $transaction->update($updateData);

        return $transaction->fresh();
    }
}
