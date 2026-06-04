<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Payment received</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.5;color:#1f2937;">
<h2 style="color:#15803d;">Payment received</h2>
<p>Thank you{{ $txn->customer_first_name ? ', '.$txn->customer_first_name : '' }}. Your payment has been confirmed.</p>
<table cellpadding="8" style="border-collapse:collapse;background:#f3f4f6;border-radius:6px;">
    <tr><td style="font-weight:bold;">Amount</td><td>{{ $txn->currency }} {{ number_format((float) $txn->amount, 2) }}</td></tr>
    <tr><td style="font-weight:bold;">Order</td><td>{{ $txn->basket_id }}</td></tr>
    @if ($txn->payfast_transaction_id)
    <tr><td style="font-weight:bold;">PayFast TXN ID</td><td><code>{{ $txn->payfast_transaction_id }}</code></td></tr>
    @endif
    <tr><td style="font-weight:bold;">Date</td><td>{{ optional($txn->completed_at)->format('Y-m-d H:i') }}</td></tr>
</table>
<p style="color:#6b7280;font-size:13px;">Keep this email for your records. Questions? Email {{ $branding['support_email'] }}.</p>
</body>
</html>
