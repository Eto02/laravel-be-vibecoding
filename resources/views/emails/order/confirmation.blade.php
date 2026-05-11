<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Order Confirmation</title></head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2>Order Confirmed!</h2>
    <p>Hi {{ $order->user->name }}, thank you for your order.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Order Number</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $order->order_number }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Status</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $order->status->value }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Total</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">Rp {{ number_format($order->total / 100, 0, ',', '.') }}</td></tr>
        <tr><td style="padding: 8px;"><strong>Payment Deadline</strong></td><td style="padding: 8px;">{{ $order->payment_due_at->format('d M Y, H:i') }}</td></tr>
    </table>

    <h3>Items</h3>
    @foreach($order->items as $item)
        <p>{{ $item->product_snapshot['product_name'] }} — {{ $item->quantity }} x Rp {{ number_format($item->unit_price / 100, 0, ',', '.') }}</p>
    @endforeach

    <p style="color: #666; font-size: 12px; margin-top: 32px;">Please complete your payment before the deadline to avoid automatic cancellation.</p>
</body>
</html>
