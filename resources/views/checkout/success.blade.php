<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment successful</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{margin:0;font-family:system-ui,sans-serif;background:linear-gradient(135deg,#ecfdf5,#fff,#d1fae5);min-height:100vh;display:flex;align-items:center;justify-content:center;color:#111827;}
        .card{background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.08);padding:32px;max-width:440px;width:90%;text-align:center;}
        .icon{width:64px;height:64px;background:#dcfce7;color:#16a34a;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;}
        h1{font-size:24px;margin:0 0 8px;}
        .muted{color:#6b7280;font-size:14px;}
        .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:14px;text-align:left;}
        .row:last-child{border:none;}
        .countdown{font-size:48px;font-weight:700;color:#16a34a;margin-top:24px;}
    </style>
</head>
<body>
<div class="card">
    <div class="icon">✓</div>
    <h1>Payment successful</h1>
    <p class="muted">Your payment has been confirmed.</p>
    <div style="margin-top:16px;">
        <div class="row"><span class="muted">Amount</span><span><strong>{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</strong></span></div>
        <div class="row"><span class="muted">Order</span><code>{{ $transaction->basket_id }}</code></div>
        @if ($transaction->payfast_transaction_id)
        <div class="row"><span class="muted">PayFast TXN</span><code>{{ $transaction->payfast_transaction_id }}</code></div>
        @endif
    </div>
    <div class="countdown" id="cd">{{ $auto_redirect_seconds }}</div>
    <p class="muted">Returning to {{ parse_url($branding['storefront_url'], PHP_URL_HOST) }}…</p>
    <p style="margin-top:8px;"><a href="{{ $redirect_url }}" style="color:#6b7280;font-size:12px;">Click here if you are not redirected</a></p>
</div>
<script>
(function(){
    var s = {{ (int) $auto_redirect_seconds }};
    var el = document.getElementById('cd');
    var t = setInterval(function(){
        s--; el.textContent = s;
        if (s <= 0){clearInterval(t); window.location.href = @json($redirect_url);}
    }, 1000);
})();
</script>
</body>
</html>
