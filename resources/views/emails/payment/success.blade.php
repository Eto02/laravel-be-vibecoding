<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Payment Successful</title></head>
<body style="font-family: sans-serif; color: #333;">
    <h2>Payment Successful</h2>
    <p>Hi {{ $payment->order->user->name }},</p>
    <p>Your payment for order <strong>#{{ $payment->order->order_number }}</strong> has been confirmed.</p>
    <table style="border-collapse: collapse; width: 100%; max-width: 500px;">
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">Order Number</td>
            <td style="padding: 8px; border: 1px solid #ddd;"><strong>#{{ $payment->order->order_number }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">Amount Paid</td>
            <td style="padding: 8px; border: 1px solid #ddd;">Rp {{ number_format($payment->amount / 100, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">Payment Method</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ strtoupper(str_replace('_', ' ', $payment->method)) }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;">Gateway</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ ucfirst($payment->gateway) }}</td>
        </tr>
    </table>
    <p style="margin-top: 24px;">Your order is now being processed by the seller.</p>
    <p>Thank you for shopping with us!</p>
</body>
</html>
