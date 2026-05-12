<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Payment Update</title></head>
<body style="font-family: sans-serif; color: #333;">

@if ($payment->status->value === 'expired')
    <h2 style="color: #e67e22;">Payment Expired</h2>
    <p>Hi {{ $payment->order->user->name }},</p>
    <p>Your payment for order <strong>#{{ $payment->order->order_number }}</strong> has <strong>expired</strong> because it was not completed within the time limit.</p>
    <p>Your order is still active. You can initiate a new payment anytime before the order deadline.</p>
@else
    <h2 style="color: #e74c3c;">Payment Failed</h2>
    <p>Hi {{ $payment->order->user->name }},</p>
    <p>Your payment for order <strong>#{{ $payment->order->order_number }}</strong> could not be processed.</p>
    <p>Please try again using a different payment method.</p>
@endif

<table style="border-collapse: collapse; width: 100%; max-width: 500px; margin-top: 16px;">
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Order Number</td>
        <td style="padding: 8px; border: 1px solid #ddd;"><strong>#{{ $payment->order->order_number }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Amount</td>
        <td style="padding: 8px; border: 1px solid #ddd;">Rp {{ number_format($payment->amount / 100, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Gateway</td>
        <td style="padding: 8px; border: 1px solid #ddd;">{{ ucfirst($payment->gateway) }}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">Status</td>
        <td style="padding: 8px; border: 1px solid #ddd; color: #e67e22;">{{ ucfirst($payment->status->value) }}</td>
    </tr>
</table>

<p style="margin-top: 24px; color: #666; font-size: 13px;">
    If you believe this is an error or need assistance, please contact our support team.
</p>
</body>
</html>
