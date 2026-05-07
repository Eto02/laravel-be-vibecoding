<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Your Order Has Been Shipped</title></head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Your Order is On the Way!</h2>
    <p>Hi {{ $order->user->name }}, your order has been shipped.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Order Number</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $order->order_number }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Courier</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ strtoupper($order->shipping_courier) }} — {{ $order->shipping_service }}</td></tr>
        <tr><td style="padding: 8px;"><strong>Tracking Number</strong></td><td style="padding: 8px;">{{ $order->tracking_number }}</td></tr>
    </table>

    <p>You can track your shipment using the tracking number above on the courier's website.</p>
    <p>Once you receive your package, please confirm receipt in the app.</p>

    <p style="color: #666; font-size: 12px; margin-top: 32px;">If you have any issues, please contact support.</p>
</body>
</html>
