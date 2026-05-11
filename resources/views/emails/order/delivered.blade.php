<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Your Order Has Been Delivered</title></head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Your Order Has Been Delivered!</h2>
    <p>Hi {{ $order->user->name }}, your order has been delivered.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Order Number</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $order->order_number }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Total</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">Rp {{ number_format($order->total / 100, 0, ',', '.') }}</td></tr>
        <tr><td style="padding: 8px;"><strong>Status</strong></td><td style="padding: 8px;">Delivered</td></tr>
    </table>

    <p>Thank you for shopping with us! If everything is satisfactory, your order will be automatically completed in 3 days.</p>
    <p>If you have any issues with your order, you can file a dispute within the app.</p>

    <p style="color: #666; font-size: 12px; margin-top: 32px;">If you have any questions, please contact support.</p>
</body>
</html>
