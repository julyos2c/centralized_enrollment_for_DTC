    <nav class="public-navbar">
        <div class="public-navbar-container">
            <a href="{{ route('landing') }}" class="public-brand">
                <img
                    src="{{ asset('images/DTC-LOGO.webp') }}"
                    alt="DTC Logo"
                    class="public-logo"
                >
                <div class="public-brand-text">
                    <h1>DTC Nexus</h1>
                    <p>Danao Technological College</p>
                </div>
            </a>


            <div class="public-nav-links">

                <a href="{{ route('landing') }}"
                class="{{ request()->routeIs('landing') ? 'active' : '' }}">
                    Home
                </a>

                <a href="{{ route('public.application.create') }}"
                class="{{ request()->routeIs('public.application.create') ? 'active' : '' }}">
                    Admissions
                </a>

                <a href="{{ route('public.application.status.form') }}"
                class="{{ request()->routeIs('public.application.status.form') ? 'active' : '' }}">
                    Check Status
                </a>

            </div>

            {{-- Dark Mode Toggle --}}
            <div class="public-navbar-theme">
                <button
                    type="button"
                    id="darkModeToggle"
                    class="theme-toggle-btn"
                    aria-label="Enable dark mode"
                    aria-pressed="false"
                    title="Enable dark mode">
                    <i data-lucide="moon" id="darkModeIcon" aria-hidden="true"></i>
                </button>
            </div>

            <button type="button" class="public-signin" data-signin-modal="trigger">
                <i data-lucide="arrow-right"></i> Sign in
            </button>
            
            <button
                type="button"
                class="public-navbar-toggle"
                id="applicationNavbarToggle"
                aria-label="Toggle navigation"
                aria-controls="applicationMobileNav"
                aria-expanded="false"
            >
                <i data-lucide="menu"></i>
            </button>

        </div>

        <div class="public-mobile-nav" id="applicationMobileNav">

            <a href="{{ route('landing') }}"
            class="{{ request()->routeIs('landing') ? 'active' : '' }}">
                Home
            </a>

            <a href="{{ route('public.application.create') }}"
            class="{{ request()->routeIs('public.application.create') ? 'active' : '' }}">
                Admissions
            </a>

            <a href="{{ route('public.application.status.form') }}"
            class="{{ request()->routeIs('public.application.status.form') ? 'active' : '' }}">
                Check Status
            </a>

            <button type="button" class="public-signin" data-signin-modal="trigger">
                <i data-lucide="arrow-right"></i> Sign in
            </button>

        </div>

    </nav>

<script>
    const applicationNavbarToggle =
        document.getElementById('applicationNavbarToggle');

    const applicationMobileNav =
        document.getElementById('applicationMobileNav');

    applicationNavbarToggle.addEventListener('click', function () {
        applicationMobileNav.classList.toggle('open');

        const isOpen = applicationMobileNav.classList.contains('open');

        this.setAttribute('aria-expanded', isOpen);
    });

    function closeApplicationMobileNav() {
        applicationMobileNav.classList.remove('open');
        applicationNavbarToggle.setAttribute('aria-expanded', 'false');
    }

    applicationMobileNav.addEventListener('click', function (event) {
        if (event.target.closest('a, button')) closeApplicationMobileNav();
    });
    function dismissApplicationNavOutside(event) {
        if (!applicationNavbarToggle.contains(event.target) && !applicationMobileNav.contains(event.target)) {
            closeApplicationMobileNav();
        }
    }
    document.addEventListener('pointerdown', dismissApplicationNavOutside, { passive: true });
    document.addEventListener('touchstart', dismissApplicationNavOutside, { passive: true });
    document.addEventListener('click', dismissApplicationNavOutside);
    document.addEventListener('scroll', closeApplicationMobileNav, { passive: true, capture: true });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeApplicationMobileNav();
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) closeApplicationMobileNav();
    });
</script>
