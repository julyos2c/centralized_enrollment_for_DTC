<!DOCTYPE html>
<html lang="en">

<head>
    @include('partials.pwa-head')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', 'DTC Nexus')
    </title>

    {{-- Link preview (Open Graph / Twitter) --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="DTC Nexus">
    <meta property="og:title" content="DTC Nexus — Admissions & Enrollment System">
    <meta property="og:description" content="Apply and enroll online at Danao Technological College.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">

    {{-- Lucide Icons --}}
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    {{-- Poppins --}}
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/application.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public-navbar.css') }}">
    {{-- DTC Public Theme --}}
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    <link rel="stylesheet" href="{{ asset('css/signin-modal.css') }}">
    {{-- DTC Theme (dark mode support) --}}
    <link rel="stylesheet" href="{{ asset('css/dtc-theme.css') }}">
    <script src="{{ asset('js/signin-modal.js') }}" defer></script>
    {{-- Page-specific styles --}}
    @yield('styles')

    {{-- DTC Nexus theme preload: prevents light-mode flash on saved dark theme --}}
    <script>
        (function () {
            try {
                var saved;
                try { saved = localStorage.getItem('dtc-ems-theme'); } catch (e) {}
                if (saved === 'dark' || (saved !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dtc-dark-preload');
                    document.documentElement.classList.add('dtc-dark');
                }
            } catch (e) {}
        })();
    </script>
</head>

<body>


    @include('partials.public-navbar')
    @include('partials.signin-modal')

    {{-- =========================================================
        PAGE CONTENT
    ========================================================== --}}
    <main>
        @yield('content')
    </main>


    {{-- =========================================================
        FOOTER
    ========================================================== --}}
    <footer class="site-footer">
        © {{ date('Y') }} Danao Technological College —
        Enrollment Management System. All rights reserved.
    </footer>


    <script src="{{ asset('js/session-feedback.js') }}" defer></script>
    <script src="{{ asset('js/dtc-app.js') }}" defer></script>
    <script src="{{ asset('js/dtc-feedback.js') }}" defer></script>

    {{-- DTC toast container (flash messages → toasts) --}}
    <x-dtc.toast />
    <x-dtc.confirm-modal />

    {{-- Page-specific scripts --}}
    @yield('scripts')
    @include('components.dtc.password-guidance')

    {{-- Lucide icon initialisation — runs after all page content is in the DOM --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) lucide.createIcons();
        });
    </script>

</body>

</html>
