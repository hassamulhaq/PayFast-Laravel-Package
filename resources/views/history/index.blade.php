<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{margin:0;font-family:system-ui,sans-serif;background:#f9fafb;color:#111827;padding:24px 16px;}
        .container{max-width:1200px;margin:0 auto;}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;}
        h1{font-size:22px;margin:0;}
        .muted{color:#6b7280;font-size:14px;}
        .head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;}
        table{width:100%;border-collapse:collapse;font-size:14px;}
        th{text-align:left;text-transform:uppercase;font-size:11px;color:#6b7280;padding:8px;border-bottom:1px solid #e5e7eb;}
        td{padding:8px;border-bottom:1px solid #f3f4f6;}
        .badge{display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;}
        .badge.success{background:#dcfce7;color:#166534;}
        .badge.failed{background:#fee2e2;color:#991b1b;}
        .badge.pending{background:#e5e7eb;color:#374151;}
        .badge.initiated{background:#e0e7ff;color:#3730a3;}
        code{background:#f3f4f6;padding:1px 4px;border-radius:3px;font-size:12px;}
        a{color:#1f2937;text-decoration:underline;text-underline-offset:2px;}
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="head">
            <h1>{{ $is_admin_view ? 'All payments' : 'My payments' }}</h1>
            <p class="muted">Questions? Email <a href="mailto:{{ $branding['support_email'] }}">{{ $branding['support_email'] }}</a> with your <strong>Order ID</strong>.</p>
        </div>
        @if ($payments->count() === 0)
            <p class="muted" style="padding:24px 0;text-align:center;">No payments yet.</p>
        @else
        <table>
            <thead>
                <tr>
                    <th>Date</th><th>Order</th><th>Name</th><th>Email</th><th>Phone</th>
                    <th style="text-align:right;">Amount</th><th>Status</th><th>PayFast TXN</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($payments as $p)
                <tr>
                    <td>{{ $p->created_at->format('Y-m-d H:i') }}</td>
                    <td><code>{{ $p->basket_id }}</code></td>
                    <td>{{ trim(($p->customer_first_name ?? '').' '.($p->customer_last_name ?? '')) ?: '—' }}</td>
                    <td>{{ $p->customer_email ?? '—' }}</td>
                    <td><code>{{ $p->customer_mobile ?? '—' }}</code></td>
                    <td style="text-align:right;"><strong>{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</strong></td>
                    <td><span class="badge {{ $p->status }}">{{ $p->status }}</span></td>
                    <td><code title="{{ $p->payfast_transaction_id }}">{{ $p->payfast_transaction_id ? substr($p->payfast_transaction_id, 0, 8).'…'.substr($p->payfast_transaction_id, -7) : '—' }}</code></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if ($payments->hasPages())
            <div style="margin-top:16px;">{!! $payments->links() !!}</div>
        @endif
        @endif
    </div>
</div>
</body>
</html>
