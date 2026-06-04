<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Welcome</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.5;color:#1f2937;">
<h2>Welcome, {{ $name }}!</h2>
<p>An account was created for you on <strong>{{ $branding['app_name'] }}</strong> when you completed your purchase.</p>
<p>Sign-in credentials:</p>
<table cellpadding="8" style="border-collapse:collapse;background:#f3f4f6;border-radius:6px;">
    <tr><td style="font-weight:bold;">Email</td><td>{{ $email }}</td></tr>
    <tr><td style="font-weight:bold;">Temporary password</td><td><code>{{ $password }}</code></td></tr>
</table>
<p><a href="{{ $loginUrl }}" style="display:inline-block;background:#111827;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;">Log in</a></p>
<p style="color:#6b7280;font-size:13px;">For security, please change this password after your first login.</p>
</body>
</html>
