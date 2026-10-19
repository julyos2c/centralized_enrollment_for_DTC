<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTC EMS — Welcome</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }

        body {
            min-height: 100vh;
            background: #0F4CDB;
            overflow: hidden;
        }

        /* ── Background Image ─────────────────────── */
        .bg {
            position: fixed;
            inset: 0;
            background: url('{{ asset("images/dtc-building.svg") }}') center center / cover no-repeat;
            filter: brightness(0.45) saturate(1.2);
            z-index: 0;
        }

        /* Blue overlay */
        .bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(10, 40, 140, 0.55);
        }

        /* ── Top Navbar ───────────────────────────── */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;

            background: rgba(10, 40, 140, 0.85);
            backdrop-filter: blur(8px);

            padding: 12px 32px;

            display: flex;
            justify-content: flex-start;
            align-items: center;

            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .navbar-brand{
            display:flex;
            align-items:center;
            gap:14px;
        }

        .navbar-brand img{
            width:52px;
            height:52px;
            object-fit:contain;
        }

        .brand-text h1{
            color:#fff;
            font-size:24px;
            font-weight:700;
            line-height:1;
        }

        .brand-text p{
            color:rgba(255,255,255,.75);
            font-size:13px;
            margin-top:4px;
        }
        /* ── Hero Content ─────────────────────────── */
        .hero {
            position: relative;
            z-index: 5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 80px 24px 40px;
        }

        .welcome-label {
            font-size: 13px;
            font-weight: 700;
            color: rgba(255,255,255,0.7);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .hero h1 {
            font-size: clamp(20px, 3vw, 32px);
            font-weight: 800;
            color: #ffffff;
            line-height: 1.3;
            max-width: 750px;
            margin-bottom: 8px;
        }

        .hero .school-name {
            font-size: clamp(22px, 3.5vw, 36px);
            font-weight: 800;
            color: #FFC72C;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: clamp(13px, 1.5vw, 15px);
            color: rgba(255,255,255,0.75);
            line-height: 1.7;
            max-width: 640px;
            margin-bottom: 36px;
        }

        .btn-signin {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #FFC72C;
            color: #1E293B;
            padding: 16px 48px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(255,199,44,0.4);
            letter-spacing: 0.5px;
        }

        .btn-signin:hover {
            background: #f0b800;
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(255,199,44,0.5);
            color: #1E293B;
            text-decoration: none;
        }

        /* Feature pills */
        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 40px;
        }

        .feature-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            padding: 8px 16px;
            font-size: 12px;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            backdrop-filter: blur(4px);
        }

        .feature-pill i {
            color: #FFC72C;
            font-size: 12px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            z-index: 10;
            padding: 12px 32px;
            text-align: center;
            font-size: 11px;
            color: rgba(255,255,255,0.4);
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 18px; }
            .hero .school-name { font-size: 22px; }
            .btn-signin { padding: 14px 36px; font-size: 15px; }
        }
    </style>
</head>
<body>

    <div class="bg"></div>

    {{-- Navbar --}}
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="{{ asset('images/DTC-LOGO.png') }}" alt="DTC Logo">

            <div class="brand-text">
                <h1>DTC EMS</h1>
                <p>Danao Technological College</p>
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <div class="hero">
        <div class="welcome-label">Welcome to</div>
        <h1>Web-Based Integrated Certificate Appointment and Payment System with Centralized Enrollment for</h1>
        <div class="school-name">Danao Technological College</div>
        <p>
            Streamlining enrollment, certificate requests, appointment scheduling,
            and payment services through one secure and convenient online platform.
        </p>
        <a href="{{ route('login') }}" class="btn-signin">
            Sign In
        </a>

        <div class="features">
            <div class="feature-pill"><i class="fas fa-file-alt"></i> Online Enrollment</div>
            <div class="feature-pill"><i class="fas fa-calendar-check"></i> Certificate Appointments</div>
            <div class="feature-pill"><i class="fas fa-money-bill-wave"></i> Payment Processing</div>
            <div class="feature-pill"><i class="fas fa-bell"></i> Real-time Notifications</div>
            <div class="feature-pill"><i class="fas fa-shield-alt"></i> Role-Based Access</div>
        </div>
    </div>

    <div class="footer">
        © {{ date('Y') }} Danao Technological College — Enrollment Management System. All rights reserved.
    </div>

</body>
</html>