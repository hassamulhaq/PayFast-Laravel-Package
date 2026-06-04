<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Payment failed</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.5;color:#1f2937;">
<h2 style="color:#b91c1c;">Payment failed</h2>
<p>Hi{{ $txn->customer_first_name ? ' '.$txn->customer_first_name : '' }},</p>
<p>Your recent payment attempt could not be completed. No charge has been made.</p>
<table cellpadding="8" style="border-collapse:collapse;background:#fef2f2;border-radius:6px;">
    <tr><td style="font-weight:bold;">Amount</td><td>{{ $txn->currency }} {{ number_format((float) $txn->amount, 2) }}</td></tr>
    <tr><td style="font-weight:bold;">Order</td><td>{{ $txn->basket_id }}</td></tr>
    @if ($txn->payfast_status_code)
    <tr><td style="font-weight:bold;">Status code</td><td>{{ $txn->payfast_status_code }}</td></tr>
    @endif
    @if ($txn->payfast_status_message)
    <tr><td style="font-weight:bold;">Message</td><td>{{ $txn->payfast_status_message }}</td></tr>
    @endif
</table>
<p>Try again at any time. Continued issues? Reply to this email or contact {{ $branding['support_email'] }}.</p>
</body>
</html>
