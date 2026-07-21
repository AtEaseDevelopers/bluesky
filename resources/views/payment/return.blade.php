<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('orders.qr.return_title') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/favicon.png') }}">
    <style>
        :root { --deep:#023e7d; --accent:#ff5d3b; --muted:#4a6072; --line:#d6e2ec; --ok:#1f9d55; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(120deg,#023e7d 0%,#0a6e8a 100%); font-family:'Manrope',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; padding:20px; }
        .card { background:#fff; border-radius:1.1rem; box-shadow:0 20px 50px rgba(0,0,0,.2); max-width:420px; width:100%; padding:40px 28px; text-align:center; }
        .icon { width:84px; height:84px; margin:0 auto 18px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
        .icon svg { width:46px; height:46px; }
        .ok   { background:#e9f8ef; } .ok svg   { stroke:var(--ok); }
        .fail { background:#fdecea; } .fail svg { stroke:var(--accent); }
        .wait { background:#eaf1f8; } .wait svg { stroke:var(--deep); }
        h1 { font-size:1.5rem; color:var(--deep); margin:0 0 8px; font-weight:800; }
        p { color:var(--muted); font-size:1.02rem; line-height:1.5; margin:0 0 6px; }
        .ref { margin-top:14px; font-size:.9rem; color:var(--muted); }
        .ref b { color:var(--deep); }
    </style>
</head>
<body>
    <div class="card">
        @if ($status === 'success')
            <div class="icon ok"><svg viewBox="0 0 52 52" fill="none" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 27l8 8 16-16"/></svg></div>
            <h1>{{ __('orders.qr.return_success_title') }}</h1>
            <p>{{ __('orders.qr.return_success_body') }}</p>
        @elseif (in_array($status, ['failed', 'fail', 'cancel', 'cancelled'], true))
            <div class="icon fail"><svg viewBox="0 0 52 52" fill="none" stroke-width="4" stroke-linecap="round"><path d="M17 17l18 18M35 17L17 35"/></svg></div>
            <h1>{{ __('orders.qr.return_failed_title') }}</h1>
            <p>{{ __('orders.qr.return_failed_body') }}</p>
        @else
            <div class="icon wait"><svg viewBox="0 0 52 52" fill="none" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M26 14v12l8 5"/></svg></div>
            <h1>{{ __('orders.qr.return_pending_title') }}</h1>
            <p>{{ __('orders.qr.return_pending_body') }}</p>
        @endif
        @if ($orderId)
            <div class="ref">{{ __('orders.qr.return_ref') }}: <b>{{ $orderId }}</b></div>
        @endif
    </div>
</body>
</html>
