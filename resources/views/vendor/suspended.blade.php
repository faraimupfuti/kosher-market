<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendor selling restricted | Kosher Market</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f8fb;color:#111827;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .card{width:min(560px,calc(100% - 40px));background:#fff;border-radius:20px;padding:36px;box-shadow:0 12px 40px rgba(0,0,0,.08);text-align:center}
        .icon{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;margin:0 auto 18px;background:#fee2e2;color:#b91c1c;font-size:28px}
        h1{margin:0 0 12px;font-size:28px}.muted{color:#6b7280;line-height:1.6}.reason{margin-top:20px;padding:16px;border-radius:12px;background:#f8fafc;text-align:left}.label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b7280}.reason p{margin:6px 0 0;white-space:pre-wrap}.status{display:inline-block;margin-top:18px;padding:7px 12px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:800;text-transform:uppercase}
    </style>
</head>
<body>
    <main class="card">
        <div class="icon">!</div>
        <h1>Selling is currently restricted</h1>
        <p class="muted">Your Kosher Market vendor account is currently unable to sell or manage marketplace listings.</p>
        <span class="status">{{ ucfirst($vendor->status) }}</span>
        @if($vendor->status === 'banned' && $vendor->ban_reason)
            <div class="reason">
                <div class="label">Reason</div>
                <p>{{ $vendor->ban_reason }}</p>
            </div>
        @endif
        <p class="muted">If you believe this was a mistake, contact marketplace administration.</p>
    </main>
</body>
</html>
