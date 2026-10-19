<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Server Error | DTC EMS</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 24px;
            padding: 50px 40px;
            text-align: center;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 24px 60px rgba(0,0,0,0.3);
        }
        .icon-wrap {
            width: 90px; height: 90px;
            border-radius: 24px;
            background: #F1F5F9;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
        }
        .icon-wrap i { font-size: 38px; color: #64748B; }
        .code {
            font-size: 72px; font-weight: 800;
            color: #1E293B; line-height: 1;
            margin-bottom: 8px;
        }
        h1 { font-size: 22px; font-weight: 700; color: #1E293B; margin-bottom: 12px; }
        p  { font-size: 14px; color: #64748B; line-height: 1.7; margin-bottom: 28px; }
        .btn-home {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, #0F4CDB, #1a5feb);
            color: #fff; padding: 12px 28px; border-radius: 12px;
            text-decoration: none; font-size: 14px; font-weight: 700;
            box-shadow: 0 4px 16px rgba(15,76,219,0.35);
            transition: all 0.2s;
        }
        .btn-home:hover { transform: translateY(-2px); color: #fff; text-decoration: none; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            background: #F1F5F9; color: #64748B;
            padding: 12px 28px; border-radius: 12px;
            text-decoration: none; font-size: 14px; font-weight: 600;
            margin-left: 10px; transition: all 0.2s;
        }
        .btn-back:hover { background: #E2E8F0; color: #1E293B; text-decoration: none; }
        .badge {
            display: inline-block; background: #F1F5F9; color: #475569;
            font-size: 11px; font-weight: 700; padding: 4px 12px;
            border-radius: 20px; margin-bottom: 16px; letter-spacing: 0.5px;
        }
        .error-detail {
            background: #F8FAFC; border-radius: 12px;
            padding: 14px 18px; margin-bottom: 24px;
            border-left: 4px solid #CBD5E1; text-align: left;
        }
        .error-detail p {
            font-size: 12px; color: #64748B; margin: 0; line-height: 1.6;
        }
        .brand {
            font-size: 13px; color: #94A3B8; margin-top: 28px;
            padding-top: 20px; border-top: 1px solid #F1F5F9;
        }
        .brand strong { color: #0F4CDB; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">
            <i class="fas fa-server"></i>
        </div>
        <div class="badge">ERROR 500</div>
        <div class="code">500</div>
        <h1>Internal Server Error</h1>
        <p>
            Something went wrong on our end. Our team has been notified
            and we're working to fix this as soon as possible.
        </p>

        <div class="error-detail">
            <p>
                <i class="fas fa-info-circle mr-2" style="color:#0F4CDB;"></i>
                If this problem persists, please contact your system administrator
                or try again after a few minutes.
            </p>
        </div>

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