<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Confirm payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#f7f8fa,#fff,#f3f4f6);min-height:100vh;color:#111827;}
        .container{max-width:720px;margin:40px auto;padding:0 16px;}
        .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.06);padding:24px;margin-bottom:16px;}
        h1{font-size:24px;margin:0 0 8px;}
        .muted{color:#6b7280;font-size:14px;}
        .amount{font-size:36px;font-weight:700;}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;}
        @media (max-width:600px){.grid{grid-template-columns:1fr;}}
        label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:4px;}
        input{width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;color:#111827;box-sizing:border-box;}
        button{margin-top:20px;width:100%;padding:14px;font-size:16px;font-weight:600;color:#fff;background:linear-gradient(120deg,#004122,#06743f,#1a1a1a);background-size:200% 200%;border:none;border-radius:6px;cursor:pointer;animation:shimmer 3.2s ease infinite;}
        button[disabled]{opacity:.6;cursor:wait;}
        @keyframes shimmer{0%{background-position:0%}50%{background-position:100%}100%{background-position:0%}}
        .error{margin-top:12px;padding:10px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:6px;font-size:14px;}
        .toast{position:fixed;top:16px;right:16px;background:#10b981;color:#fff;padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.15);font-size:14px;max-width:360px;}
    </style>
</head>
<body>
@if ($new_user_created && $transaction->customer_email)
    <div class="toast">Account created — temporary password emailed to {{ $transaction->customer_email }}.</div>
@endif
<div class="container">
    <div class="card">
        <h1>Confirm payment</h1>
        <p class="muted">Review your details, then continue to PayFast to complete the payment. Your payment history and status are stored here for full transparency.</p>
        <div style="margin-top:20px;padding:16px;background:#f3f4f6;border-radius:8px;">
            <div class="muted">Amount</div>
            <div class="amount">{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</div>
            <div class="muted" style="margin-top:6px;">Order: <code>{{ $transaction->basket_id }}</code></div>
        </div>
        <div class="grid">
            <div><label>First name</label><input value="{{ $transaction->customer_first_name }}" readonly></div>
            <div><label>Last name</label><input value="{{ $transaction->customer_last_name }}" readonly></div>
            <div><label>Email</label><input value="{{ $transaction->customer_email }}" readonly></div>
            <div><label>Mobile</label><input value="{{ $transaction->customer_mobile }}" readonly></div>
        </div>
        <button id="pay-btn" type="button">Pay {{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</button>
        <div id="pay-error" class="error" style="display:none;"></div>
        <p class="muted" style="text-align:center;margin-top:12px;">Once you click Pay, you will be redirected to PayFast for secure checkout.</p>
    </div>
</div>
<form id="hosted-form" method="POST" style="display:none;"></form>
<script>
(function(){
    var btn = document.getElementById('pay-btn');
    var err = document.getElementById('pay-error');
    var form = document.getElementById('hosted-form');
    btn.addEventListener('click', function(){
        btn.disabled = true;
        btn.textContent = 'Redirecting to PayFast…';
        err.style.display = 'none';
        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch(@json($pay_url), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf ? csrf.content : ''
            },
            credentials: 'same-origin'
        }).then(function(r){return r.json();}).then(function(data){
            if (!data.action_url || !data.fields){
                throw new Error(data.error || 'Payment gateway error.');
            }
            form.action = data.action_url;
            Object.keys(data.fields).forEach(function(k){
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = k; i.value = data.fields[k];
                form.appendChild(i);
            });
            form.submit();
        }).catch(function(e){
            btn.disabled = false;
            btn.textContent = 'Pay {{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}';
            err.textContent = e.message;
            err.style.display = 'block';
        });
    });
})();
</script>
</body>
</html>
