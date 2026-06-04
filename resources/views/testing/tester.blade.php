<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PayFast Tester</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{margin:0;font-family:system-ui,sans-serif;background:#f9fafb;padding:32px 16px;}
        .card{max-width:640px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;}
        h1{margin:0 0 8px;font-size:22px;}
        .muted{color:#6b7280;font-size:14px;margin-bottom:16px;}
        label{display:block;font-size:13px;color:#374151;margin:12px 0 4px;}
        input{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:4px;box-sizing:border-box;}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        button{margin-top:20px;width:100%;padding:12px;background:#111827;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;}
    </style>
</head>
<body>
<div class="card">
    <h1>PayFast Tester</h1>
    <p class="muted">Generates a signed checkout URL and forwards you to <code>/payment/checkout</code>.</p>
    <form method="POST" action="{{ route('payfast.testing.generate') }}">
        @csrf
        <label>Checkout ID</label>
        <input name="checkout_id" value="{{ $defaults['checkout_id'] }}" required>
        <div class="grid">
            <div><label>Amount</label><input name="amount" type="number" step="0.01" value="{{ $defaults['amount'] }}" required></div>
            <div><label>Currency</label><input name="currency" value="{{ $defaults['currency'] }}" required></div>
        </div>
        <div class="grid">
            <div><label>First name</label><input name="first_name" value="{{ $defaults['first_name'] }}"></div>
            <div><label>Last name</label><input name="last_name" value="{{ $defaults['last_name'] }}"></div>
        </div>
        <label>Email</label><input name="email" type="email" value="{{ $defaults['email'] }}">
        <label>Mobile</label><input name="mobile" value="{{ $defaults['mobile'] }}">
        <label>Return URL</label><input name="return_url" value="{{ $defaults['return_url'] }}">
        <button type="submit">Sign &amp; open checkout</button>
    </form>
</div>
</body>
</html>
