<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 — Page Expired | DTC EMS</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0F4CDB 0%, #072d8a 100%);
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .card {
            background: #fff; border-radius: 24px; padding: 50px 40px;
            text-align: center; max-width: 480px; width: 100%;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
        }
        .icon-wrap {
            width: 90px; height: 90px; border-radius: 24px;
            background: #DBEAFE; display: flex; align-items: center;
            justify-content: center; margin: 0 auto 24px;
        }
        .icon-wrap i { font-size: 38px; color: #1D4ED8; }
        .code { font-size: 72px; font-weight: 800; color: #0F4CDB; line-height: 1; margin-bottom: 8px; }
        h1 { font-size: 22px; font-weight: 700; color: #1E293B; margin-bottom: 12px; }
        p  { font-size: 14px; color: #64748B; line-height: 1.7; margin-bottom: 28px; }
        .btn-home {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, #0F4CDB, #1a5feb);
            color: #fff; padding: 12px 28px; border-radius: 12px;
            text-decoration: none; font-size: 14px; font-weight: 700;
            box-shadow: 0 4px 16px rgba(15,76,219,0.35); transition: all 0.2s;
        }
        .btn-home:hover { transform: translateY(-2px); color: #fff; text-decoration: none; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            background: #F1F5F9; color: #64748B; padding: 12px 28px;
            border-radius: 12px; text-decoration: none; font-size: 14px;
            font-weight: 600; margin-left: 10px; transition: all 0.2s;
        }
        .btn-back:hover { background: #E2E8F0; color: #1E293B; text-decoration: none; }
        .badge {
            display: inline-block; background: #DBEAFE; color: #1D4ED8;
            font-size: 11px; font-weight: 700; padding: 4px 12px;
            border-radius: 20px; margin-bottom: 16px; letter-spacing: 0.5px;
        }
        .brand { font-size: 13px; color: #94A3B8; margin-top: 28px;
            padding-top: 20px; border-top: 1px solid #F1F5F9; }
        .brand strong { color: #0F4CDB; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">
            <i class="fas fa-clock"></i>
        </div>
        <div class="badge">ERROR 419</div>
        <div class="code">419</div>
        <h1>Page Expired</h1>
        <p>
            Your session has expired or the page token is no longer valid.
            This usually happens when a page is left open for too long.
            Please go back and try again.
        </p>
        <div>
            <a href="{{ url('/') }}" class="btn-home">
                <i class="fas fa-home"></i> Go to Dashboard
            </a>
            <a href="javascript:history.back()" class="btn-back">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
        </div>
        <div class="brand">
            <strong>DTC EMS</strong> — Danao Technological College
        </div>
    </div>
</body>
</html>