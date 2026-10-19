<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found | DTC EMS</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0F4CDB 0%, #072d8a 100%);
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
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
        }
        .icon-wrap {
            width: 90px; height: 90px;
            border-radius: 24px;
            background: #FEF9C3;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
        }
        .icon-wrap i { font-size: 38px; color: #D97706; }
        .code {
            font-size: 72px; font-weight: 800;
            color: #FFC72C; line-height: 1;
            margin-bottom: 8px;
            text-shadow: 2px 4px 0px rgba(255,199,44,0.2);
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
            display: inline-block; background: #FEF9C3; color: #D97706;
            font-size: 11px; font-weight: 700; padding: 4px 12px;
            border-radius: 20px; margin-bottom: 16px; letter-spacing: 0.5px;
        }
        .suggestions {
            background: #F8FAFC; border-radius: 14px;
            padding: 16px 20px; margin-bottom: 24px; text-align: left;
        }
        .suggestions p {
            font-size: 12px; font-weight: 600; color: #64748B;
            margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .suggestions a {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 0; font-size: 13px; color: #0F4CDB;
            text-decoration: none; font-weight: 500;
            border-bottom: 1px solid #E2E8F0;
        }
        .suggestions a:last-child { border-bottom: none; padding-bottom: 0; }
        .suggestions a:hover { color: #072d8a; }
        .suggestions a i { width: 16px; }
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
            <i class="fas fa-search"></i>
        </div>
        <div class="badge">ERROR 404</div>
        <div class="code">404</div>
        <h1>Page Not Found</h1>
        <p>
            The page you're looking for doesn't exist or may have been moved.
            Here are some helpful links to get you back on track.
        </p>

        <div class="suggestions">
            <p>Quick Links</p>
            <a href="{{ url('/') }}">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="{{ route('notifications.index') }}">
                <i class="fas fa-bell"></i> Notifications
            </a>
            <a href="{{ route('login') }}">
                <i class="fas fa-sign-in-alt"></i> Login Page
            </a>
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