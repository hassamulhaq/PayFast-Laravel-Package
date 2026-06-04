<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment failed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{margin:0;font-family:system-ui,sans-serif;background:linear-gradient(135deg,#fef2f2,#fff,#fee2e2);min-height:100vh;display:flex;align-items:center;justify-content:center;color:#111827;}
        .card{background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.08);padding:32px;max-width:440px;width:90%;text-align:center;}
        .icon{width:64px;height:64px;background:#fee2e2;color:#dc2626;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;}
        h1{font-size:24px;margin:0 0 8px;}
        .muted{color:#6b7280;font-size:14px;}
        .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:14px;text-align:left;}
        .row:last-child{border:none;}
        .countdown{font-size:48px;font-weight:700;color:#dc2626;margin-top:24px;}
    </style>
</head>
<body>
<div class="card">
    <div class="icon">✕</div>
    <h1>Payment failed</h1>
    <p class="muted">{{ $error ?? ($transaction?->payfast_status_message ?? 'The payment could not be completed.') }}</p>
    @if ($transaction)
    <div style="margin-top:16px;">
        <div class="row"><span class="muted">Amount</span><span><strong>{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</strong></span></div>
        <div class="row"><span class="muted">Order</span><code>{{ $transaction->basket_id }}</code></div>
        @if ($transaction->payfast_status_code)
        <div class="row"><span class="muted">Status code</span><span>{{ $transaction->payfast_status_code }}</span></div>
        @endif
    </div>
    @endif
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
