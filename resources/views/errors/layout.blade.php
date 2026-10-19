<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title>@yield('error-code') | DTC Nexus</title>
    <style>
        :root { --brand:#0f4cdb; --ink:#172033; --muted:#5b6780; --surface:#fff; --line:#e5eaf2; --accent:#d97706; --tint:#fff7df; }
        * { box-sizing:border-box; }
        body { min-height:100vh; min-height:100dvh; margin:0; padding:max(20px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(20px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left)); display:grid; place-items:center; background:radial-gradient(circle at top, #2865ea 0, #0f4cdb 38%, #082c82 100%); color:var(--ink); font:16px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        main { width:min(100%, 34rem); }
        .card { overflow:hidden; border-radius:24px; background:var(--surface); box-shadow:0 24px 60px rgb(2 18 58 / 32%); text-align:center; }
        .content { padding:clamp(28px, 7vw, 48px) clamp(20px, 7vw, 44px); }
        .icon { width:72px; height:72px; margin:0 auto 20px; display:grid; place-items:center; border-radius:20px; background:var(--tint); color:var(--accent); }
        .icon svg { width:38px; height:38px; stroke:currentColor; stroke-width:1.8; fill:none; stroke-linecap:round; stroke-linejoin:round; }
        .eyebrow { display:inline-block; padding:4px 10px; border-radius:999px; background:var(--tint); color:var(--accent); font-size:.75rem; font-weight:750; letter-spacing:.08em; }
        .code { margin:14px 0 4px; color:var(--accent); font-size:clamp(3.5rem, 18vw, 5rem); font-weight:800; letter-spacing:-.08em; line-height:1; }
        h1 { margin:0; font-size:clamp(1.35rem, 5vw, 1.7rem); line-height:1.25; }
        .message { max-width:30rem; margin:14px auto 0; color:var(--muted); font-size:.95rem; }
        .notice { margin:22px 0 0; padding:13px 14px; border:1px solid var(--line); border-radius:12px; background:#f8fafc; color:var(--muted); font-size:.875rem; text-align:left; }
        .actions { display:grid; gap:10px; margin-top:26px; }
        .button { min-height:48px; display:inline-flex; align-items:center; justify-content:center; border:1px solid transparent; border-radius:12px; padding:11px 18px; background:var(--brand); color:#fff; font-weight:700; text-decoration:none; }
        .button:hover { background:#0b3daf; color:#fff; }
        .button:focus-visible { outline:3px solid #fbbf24; outline-offset:3px; }
        .button-secondary { border-color:#cbd5e1; background:#fff; color:#28405f; }
        .button-secondary:hover { background:#f8fafc; color:#172033; }
        footer { padding:15px 20px; border-top:1px solid var(--line); color:#718096; font-size:.8rem; }
        footer strong { color:var(--brand); }
        @media (min-width: 420px) { .actions { grid-template-columns:1fr 1fr; } }
        @media (prefers-reduced-motion: reduce) { *,*::before,*::after { scroll-behavior:auto !important; transition:none !important; } }
    </style>
</head>
<body>
    <main>
        <section class="card" aria-labelledby="error-title">
            <div class="content">
                <div class="icon" aria-hidden="true">@yield('icon')</div>
                <span class="eyebrow">ERROR @yield('error-code')</span>
                <div class="code" aria-hidden="true">@yield('error-code')</div>
                <h1 id="error-title">@yield('heading')</h1>
                <p class="message">@yield('message')</p>
                @hasSection('notice')<div class="notice">@yield('notice')</div>@endif
                <div class="actions">@yield('actions')</div>
            </div>
            <footer><strong>DTC Nexus</strong> &middot; Danao Technological College</footer>
        </section>
    </main>
</body>
</html>
