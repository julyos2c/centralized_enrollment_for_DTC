<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — DTC EMS</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #0F4CDB;
            overflow: hidden;
        }

        /* Background */
        .bg {
            position: fixed;
            inset: 0;
            background: url('{{ asset("images/dtc-building.svg") }}') center center / cover no-repeat;
            filter: brightness(0.35) saturate(1.2) blur(3px);
            z-index: 0;
        }

        .bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(10, 40, 140, 0.6);
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;

            background: rgba(10, 40, 140, 0.9);
            backdrop-filter: blur(8px);

            display: flex;
            justify-content: flex-start;
            align-items: center;

            padding: 12px 40px;

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
            font-size:20px;
            font-weight:700;
            color:#fff;
            line-height:1;
            margin:0;
        }

        .brand-text p{
            font-size:12px;
            color:rgba(255,255,255,.75);
            margin-top:4px;
        }

        /* Card */
        .login-wrapper {
            position: relative;
            z-index: 5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 20px 40px;
        }

        .login-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.3);
        }

        /* Steps */
        .step { display: none; }
        .step.active { display: block; }

        /* Header */
        .card-logo{
        width:70px;
        height:70px;
        object-fit:contain;
        margin:0 auto 15px;
        display:block;
        }
        .card-header-logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .card-header-logo .logo-circle {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0F4CDB, #1a5feb);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 12px;
            font-size: 22px; font-weight: 800; color: #FFC72C;
        }

        .card-header-logo h2 {
            font-size: 22px;
            font-weight: 800;
            color: #1E293B;
            margin-bottom: 4px;
        }

        .card-header-logo p {
            font-size: 13px;
            color: #64748B;
        }

        .card-header-logo .user-email {
            font-size: 13px;
            color: #1E293B;
            font-weight: 600;
            background: #F1F5F9;
            border-radius: 20px;
            padding: 6px 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            cursor: pointer;
        }

        .card-header-logo .user-email i {
            color: #94A3B8;
            font-size: 11px;
        }

        /* Input */
        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .input-wrap { position: relative; }

        .input-wrap i.icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 14px;
        }

        .input-wrap input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            font-size: 14px;
            color: #1E293B;
            background: #F8FAFC;
            outline: none;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .input-wrap input:focus {
            border-color: #0F4CDB;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(15,76,219,0.1);
        }

        .input-wrap .toggle-pwd {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            cursor: pointer;
            font-size: 14px;
            transition: color 0.2s;
        }

        .input-wrap .toggle-pwd:hover { color: #0F4CDB; }

        /* reCAPTCHA */
        .recaptcha-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 18px;
        }

        /* Errors */
        .error-box {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            border-left: 4px solid #EF4444;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            color: #DC2626;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Remember */
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #64748B;
            margin-bottom: 18px;
            cursor: pointer;
        }

        .remember input { accent-color: #0F4CDB; width: 16px; height: 16px; }

        /* Buttons */
        .btn-next, .btn-signin {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #0F4CDB, #1a5feb);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 16px rgba(15,76,219,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-next:hover, .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15,76,219,0.4);
        }

        .btn-back {
            width: 100%;
            padding: 11px;
            background: #F1F5F9;
            color: #64748B;
            border: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-back:hover { background: #E2E8F0; color: #1E293B; }

        /* Divider */
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 20px 0;
        }
        .divider hr { flex:1; border:none; border-top:1px solid #E2E8F0; }
        .divider span { font-size: 11px; color: #94A3B8; font-weight: 500; }

        /* Role badges */
        .role-row {
            display: flex; flex-wrap: wrap; gap: 6px; justify-content: center;
        }
        .role-pill {
            display: flex; align-items: center; gap: 5px;
            background: #F1F5F9; border: 1px solid #E2E8F0;
            border-radius: 20px; padding: 4px 10px;
            font-size: 11px; color: #64748B; font-weight: 500;
        }
        .role-pill i { font-size: 10px; color: #0F4CDB; }

        /* Footer */
        .card-footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 11px;
            color: #94A3B8;
        }

        @media (max-width: 480px) {
            .login-card { padding: 28px 20px; }
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

{{-- Login Card --}}
<div class="login-wrapper">
    <div class="login-card">

        {{-- STEP 1: Email --}}
        <div class="step active" id="step-email">

            <div class="card-header-logo">
                <img src="{{ asset('images/DTC-LOGO.png') }}" alt="DTC Logo" class="card-logo">
                <h2>Sign In</h2>
                <p>Use your DTC EMS account</p>
            </div>

            @if ($errors->has('email') || $errors->has('captcha'))
                <div class="error-box">
                    <i class="fas fa-exclamation-circle"></i>
                    {{ $errors->first('email') ?: $errors->first('captcha') }}
                </div>
            @endif

            <form id="email-form" onsubmit="goToPassword(event)">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope icon"></i>
                        <input type="email" id="email-input" placeholder="Enter your email"
                               value="{{ old('email') }}" autocomplete="email" required>
                    </div>
                </div>
                <button type="submit" class="btn-next">
                    <i class="fas fa-arrow-right"></i> Next
                </button>
            </form>

            <div class="divider"><hr><span>Access for</span><hr></div>
            <div class="role-row">
                <div class="role-pill"><i class="fas fa-shield-alt"></i> Admin</div>
                <div class="role-pill"><i class="fas fa-id-card"></i> Registrar</div>
                <div class="role-pill"><i class="fas fa-cash-register"></i> Cashier</div>
                <div class="role-pill"><i class="fas fa-user-graduate"></i> Student</div>
                <div class="role-pill"><i class="fas fa-user-tie"></i> Alumni</div>
            </div>

            <div class="card-footer-text">
                © {{ date('Y') }} Danao Technological College
            </div>
        </div>

        {{-- STEP 2: Password --}}
        <div class="step" id="step-password">

            <div class="card-header-logo">
                <img src="{{ asset('images/DTC-LOGO.png') }}" alt="DTC Logo" class="card-logo">
                <h2>Welcome</h2>
                <div class="user-email" onclick="goBack()">
                    <i class="fas fa-user-circle"></i>
                    <span id="display-email">user@dtc.edu.ph</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <input type="hidden" name="email" id="hidden-email">

                @if ($errors->has('email'))
                    <div class="error-box">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ $errors->first('email') }}
                    </div>
                @endif

                @if ($errors->has('captcha'))
                    <div class="error-box">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ $errors->first('captcha') }}
                    </div>
                @endif

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" name="password" id="password"
                               placeholder="Enter your password"
                               autocomplete="current-password" required autofocus>
                        <i class="fas fa-eye toggle-pwd" id="togglePwd"></i>
                    </div>
                </div>

                <label class="remember">
                    <input type="checkbox" name="remember"> Remember me
                </label>

                {{-- reCAPTCHA --}}
                <div class="recaptcha-wrap">
                    <div class="g-recaptcha"
                         data-sitekey="{{ env('RECAPTCHA_SITE_KEY') }}"></div>
                </div>

                @if ($errors->has('captcha'))
                    <p style="color:#DC2626; font-size:12px; margin-bottom:12px;">
                        {{ $errors->first('captcha') }}
                    </p>
                @endif

                <button type="submit" class="btn-signin">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>

                <button type="button" class="btn-back" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </form>

            <div class="card-footer-text">
                © {{ date('Y') }} Danao Technological College
            </div>
        </div>

    </div>
</div>

<script>
    function goToPassword(e) {
        e.preventDefault();
        const email = document.getElementById('email-input').value.trim();
        if (!email) return;

        document.getElementById('hidden-email').value = email;
        document.getElementById('display-email').textContent = email;

        document.getElementById('step-email').classList.remove('active');
        document.getElementById('step-password').classList.add('active');

        setTimeout(() => {
            document.getElementById('password').focus();
        }, 100);
    }

    function goBack() {
        document.getElementById('step-password').classList.remove('active');
        document.getElementById('step-email').classList.add('active');
        setTimeout(() => {
            document.getElementById('email-input').focus();
        }, 100);
    }

    // Toggle password
    document.getElementById('togglePwd').addEventListener('click', function () {
        const pwd = document.getElementById('password');
        const type = pwd.getAttribute('type') === 'password' ? 'text' : 'password';
        pwd.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // If there's a validation error, jump to password step
    @if($errors->has('email') || $errors->has('captcha'))
        const savedEmail = "{{ old('email') }}";
        if (savedEmail) {
            document.getElementById('hidden-email').value = savedEmail;
            document.getElementById('display-email').textContent = savedEmail;
            document.getElementById('step-email').classList.remove('active');
            document.getElementById('step-password').classList.add('active');
        }
    @endif
</script>

</body>
</html>