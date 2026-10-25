<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.pwa-head')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTC Nexus — Admissions &amp; Enrollment System</title>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public-navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/signin-modal.css') }}">
    {{-- DTC Theme (dark mode support) --}}
    <link rel="stylesheet" href="{{ asset('css/dtc-theme.css') }}">
    <script src="{{ asset('js/signin-modal.js') }}" defer></script>

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


  <div class="page-view active" id="view-landing">

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
                class="nav-link {{ request()->routeIs('landing') ? 'active' : '' }}">
                    Home
                </a>

                <a href="{{ route('public.application.create') }}"
                class="nav-link {{ request()->routeIs('public.application.create') ? 'active' : '' }}">
                    Admissions
                </a>

                <a href="#services" class="nav-link">
                    Services
                </a>

                <a href="#process" class="nav-link">
                    How It Works
                </a>

                <a href="#faqs" class="nav-link">
                    FAQs
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
            id="welcomeNavbarToggle"
            aria-label="Open navigation menu"
            aria-expanded="false"
            aria-controls="welcomeMobileNav"
        >
            <i data-lucide="menu"></i>
        </button>

        </div>


    <div class="public-mobile-nav" id="welcomeMobileNav">

        <a href="{{ route('landing') }}"
           class="nav-link {{ request()->routeIs('landing') ? 'active' : '' }}">
            Home
        </a>

        <a href="{{ route('public.application.create') }}"
           class="nav-link {{ request()->routeIs('public.application.create') ? 'active' : '' }}">
            Admissions
        </a>

        <a href="#services" class="nav-link">
            Services
        </a>

        <a href="#process" class="nav-link">
            How It Works
        </a>

        <a href="#faqs" class="nav-link">
            FAQs
        </a>

        <button type="button" class="public-signin" data-signin-modal="trigger">
            <i data-lucide="arrow-right"></i> Sign in
        </button>

    </div>

    </nav>

        <section class="hero">
            <div class="container">
                <div class="hero-left">
                    <div class="hero-eyebrow">DTC Nexus &middot; Connecting Students to Their Future.</div>
                    <h1>Your DTC Services.<br><span>One Convenient Portal.</span></h1>
                    <p class="hero-desc">
                        Manage your enrollment, certificate requests, appointments, and
                        payments through one secure and centralized online platform.
                    </p>
                    <p class="hero-support">
                        Enroll. Request. Schedule. Pay. Everything you need, connected in one system.
                    </p>

                    <div class="hero-cta-group">
                        <a href="{{ route('public.application.create') }}" class="btn-primary-cta">
                            Apply for Admission <i data-lucide="arrow-right"></i>
                        </a>

                        <button type="button" class="btn-secondary-cta" data-signin-modal="trigger">
                            Sign in to your account
                        </button>
                    </div>

                    <p class="hero-explore-link">
                        New here? <a href="#services">Explore our services</a>
                    </p>

                    <!-- <div class="hero-features">
                        <div class="feature-pill"><i data-lucide="file-text"></i> Online Enrollment</div>
                        <div class="feature-pill"><i data-lucide="calendar-check"></i> Certificate Appointments</div>
                        <div class="feature-pill"><i data-lucide="banknote"></i> Payment Processing</div>
                        <div class="feature-pill"><i data-lucide="shield"></i> Role-Based Access</div>
                    </div> -->
                </div>

                <!-- <div class="hero-right">
                    <div class="hero-visual">
                        <div class="circle c1"></div>
                        <div class="circle c2"></div>
                        <div class="circle c3"></div>

                        <div class="mockup-card">
                            <div class="mockup-header">
                                <div class="dot"><img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo"></div>
                                <span>DTC Nexus &middot; Student Portal</span>
                            </div>
                            <div class="mockup-body">
                                <h4>Good morning, Student</h4>
                                <p>Here's your current activity at a glance.</p>

                                <div class="mockup-stats">
                                    <div class="mockup-stat">
                                        <div class="label">Enrollment</div>
                                        <div class="value accent">Active</div>
                                    </div>
                                    <div class="mockup-stat">
                                        <div class="label">Balance</div>
                                        <div class="value">₱0.00</div>
                                    </div>
                                </div>

                                <div class="mockup-list">
                                    <div class="mockup-list-item">
                                        <i data-lucide="file-text"></i> Certificate Request
                                        <span class="badge">Pending</span>
                                    </div>
                                    <div class="mockup-list-item">
                                        <i data-lucide="calendar-check"></i> Appointment
                                        <span class="badge">Scheduled</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
            </div>
        </section>

        <section class="services" id="services">
            <div class="container">
                <div class="section-heading reveal">
                    <div class="eyebrow">Services</div>
                    <h2>Everything, connected in one system</h2>
                    <p>One portal for every DTC service — from enrollment to certificates, appointments, and payments.</p>
                </div>

                <div class="services-carousel reveal reveal-scale">

                    <button
                        type="button"
                        class="carousel-arrow carousel-arrow-prev"
                        id="servicesPrev"
                        aria-label="Previous service"
                    >
                        <i data-lucide="chevron-left"></i>
                    </button>

                    <div class="carousel-viewport" id="servicesViewport" tabindex="0" aria-roledescription="carousel" aria-label="DTC Nexus services">
                        <div class="carousel-track" id="servicesTrack">

                            <div class="service-card" data-service-id="enrollment">
                                <div class="icon-box"><i data-lucide="graduation-cap"></i></div>
                                <h3>Online Enrollment</h3>
                                <p>Enroll and manage your student records from anywhere, anytime.</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> Click to learn more</span>
                            </div>

                            <div class="service-card" data-service-id="certificates">
                                <div class="icon-box"><i data-lucide="file-text"></i></div>
                                <h3>Certificate Requests</h3>
                                <p>Request official certificates and track their status in real time.</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> Click to learn more</span>
                            </div>

                            <div class="service-card" data-service-id="appointments">
                                <div class="icon-box"><i data-lucide="calendar-check"></i></div>
                                <h3>Appointment Scheduling</h3>
                                <p>Book a pickup or claiming schedule that works for you.</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> Click to learn more</span>
                            </div>

                            <div class="service-card" data-service-id="payments">
                                <div class="icon-box"><i data-lucide="banknote"></i></div>
                                <h3>Payment Processing</h3>
                                <p>Pay online via GCash, or walk in and pay directly at the Cashier.</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> Click to learn more</span>
                            </div>

                            <div class="service-card" data-service-id="access">
                                <div class="icon-box"><i data-lucide="shield"></i></div>
                                <h3>Role-Based Access</h3>
                                <p>Students, alumni, registrars, cashiers, and admins each get their own dashboard.</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> Click to learn more</span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="carousel-arrow carousel-arrow-next"
                        id="servicesNext"
                        aria-label="Next service"
                    >
                        <i data-lucide="chevron-right"></i>
                    </button>

                </div>

                <div class="carousel-dots" id="servicesDots" role="tablist" aria-label="Services slides"></div>
            </div>
        </section>

        <section class="process" id="process">
            <div class="container">
                <div class="section-heading reveal">
                    <div class="eyebrow">How It Works</div>
                    <h2>Four simple steps</h2>
                </div>

                <div class="process-steps reveal">
                    <div class="process-step">
                        <div class="num">1</div>
                        <h4>Enroll</h4>
                        <p>Complete your enrollment online.</p>
                    </div>
                    <div class="process-step">
                        <div class="num">2</div>
                        <h4>Request</h4>
                        <p>Submit a certificate request.</p>
                    </div>
                    <div class="process-step">
                        <div class="num">3</div>
                        <h4>Schedule</h4>
                        <p>Pick an appointment slot.</p>
                    </div>
                    <div class="process-step">
                        <div class="num">4</div>
                        <h4>Pay</h4>
                        <p>Pay via GCash QR code or walk-in.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="faqs" id="faqs">
            <div class="container">
                <div class="section-heading reveal">
                    <div class="eyebrow">FAQs</div>
                    <h2>Frequently asked questions</h2>
                    <p>Quick answers about enrollment, certificates, appointments, and payments.</p>
                </div>

                <div class="faq-list reveal">
                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I enroll through DTC Nexus?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>Sign in to the portal with your student account, go to the Enrollment section, and follow the on-screen steps to submit your enrollment for the current term.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I request a certificate?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>From your dashboard, open Certificate Requests, choose the document you need, and submit the request. You can track its status in real time until it's ready for pickup.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>How does appointment scheduling work?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>After your request is approved, you'll be able to pick an available date and time slot for claiming your certificate or completing enrollment steps in person.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>What payment options are available?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>You can pay online through GCash via QR code, or pay in person as a walk-in, with the Cashier recording your payment directly in the system.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>I forgot my password. What do I do?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>Click "Sign In," then use the Forgot Password link on the sign-in form to reset it through your registered email address.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="site-footer reveal">
            © {{ date('Y') }} Danao Technological College — DTC Nexus: Admissions &amp; Enrollment System. All rights reserved.
        </footer>

        <button
            type="button"
            class="back-to-top"
            id="backToTop"
            aria-label="Back to top"
        >
            <i data-lucide="arrow-up"></i>
        </button>
    </div>

    <script>
        // If we've just landed on /login (e.g. session-timeout redirect) or
        // a sign-in attempt failed validation, open the sign-in modal
        // automatically so the user isn't left staring at the landing page.
        @if (request()->routeIs('login') && ! $errors->any())
        document.addEventListener('DOMContentLoaded', function () {
            if (window.DtcSignInModal) window.DtcSignInModal.open();
        });
        @endif

        // -- Active navigation while scrolling -----------------------------
        (function () {
            const navLinks = document.querySelectorAll(
                '.public-nav-links .nav-link, #welcomeMobileNav .nav-link'
            );
            const sections = document.querySelectorAll('#services, #process, #faqs');

            if (!navLinks.length || !sections.length) return;

            function updateActiveNav() {
                const scrollPosition = window.scrollY + 180;

                let currentSection = '';

                sections.forEach(section => {
                    if (scrollPosition >= section.offsetTop) {
                        currentSection = section.id;
                    }
                });

                navLinks.forEach(link => {
                    link.classList.remove('active');

                    const href = link.getAttribute('href');

                    if (currentSection && href === '#' + currentSection) {
                        link.classList.add('active');
                    }
                });

                // If we're above all sections, Home is active
                if (!currentSection && window.scrollY < 300) {
                    const homeLinks = document.querySelectorAll(
                        '.public-nav-links .nav-link[href="{{ route("landing") }}"], ' +
                        '#welcomeMobileNav .nav-link[href="{{ route("landing") }}"]'
                    );

                    homeLinks.forEach(link => link.classList.add('active'));
                }
            }

            window.addEventListener('scroll', updateActiveNav, { passive: true });

            updateActiveNav();
        })();
        
        // -- Mobile nav (burger menu) -------------------------------------
        (function () {
            const toggle = document.getElementById('welcomeNavbarToggle');
            const mobileNav = document.getElementById('welcomeMobileNav');

            if (!toggle || !mobileNav) return;

            function toggleMobileNav() {
                mobileNav.classList.toggle('open');
                toggle.classList.toggle('open');

                const isOpen = mobileNav.classList.contains('open');
                toggle.setAttribute('aria-expanded', isOpen);
            }

            function closeMobileNav() {
                mobileNav.classList.remove('open');
                toggle.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }

            toggle.addEventListener('click', toggleMobileNav);

            // Close menu when clicking a mobile navigation link
            mobileNav.querySelectorAll('a, button').forEach(link => {
                link.addEventListener('click', closeMobileNav);
            });

            function dismissOutside(event) {
                if (!toggle.contains(event.target) && !mobileNav.contains(event.target)) closeMobileNav();
            }
            document.addEventListener('pointerdown', dismissOutside, { passive: true });
            document.addEventListener('touchstart', dismissOutside, { passive: true });
            document.addEventListener('click', dismissOutside);
            document.addEventListener('scroll', closeMobileNav, { passive: true, capture: true });

            // Close menu when pressing Escape
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeMobileNav();
                }
            });

            // Close menu if resized back to desktop
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 992) {
                    closeMobileNav();
                }
            });

            toggle.setAttribute('aria-expanded', 'false');
        })();
        // -- FAQs accordion -----------------------------------------------
        function toggleFaq(button) {
            const item = button.closest('.faq-item');
            const wasActive = item.classList.contains('active');

            // Close all FAQ items first (one open at a time)
            document.querySelectorAll('.faq-item').forEach(faq => faq.classList.remove('active'));

            if (!wasActive) {
                item.classList.add('active');
            }
        }

        // -- Services 3D carousel -----------------------------------------
        (function () {
            const viewport = document.getElementById('servicesViewport');
            const track = document.getElementById('servicesTrack');
            const prevBtn = document.getElementById('servicesPrev');
            const nextBtn = document.getElementById('servicesNext');
            const dotsWrap = document.getElementById('servicesDots');

            if (!viewport || !track || !prevBtn || !nextBtn || !dotsWrap) return;

            const slides = Array.from(track.children);
            const total = slides.length;
            if (total === 0) return;

            let index = 0;

            function wrap(i) {
                return (i + total) % total;
            }

            function render() {
                slides.forEach((slide, i) => {
                    slide.classList.remove('is-center', 'is-prev', 'is-next', 'is-far', 'is-far-prev', 'is-far-next');

                    if (i === index) {
                        slide.classList.add('is-center');
                    } else if (i === wrap(index - 1)) {
                        slide.classList.add('is-prev');
                    } else if (i === wrap(index + 1)) {
                        slide.classList.add('is-next');
                    } else if (i === wrap(index - 2)) {
                        slide.classList.add('is-far', 'is-far-prev');
                    } else {
                        slide.classList.add('is-far', 'is-far-next');
                    }
                });

                Array.from(dotsWrap.children).forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
            }

            function goTo(i) {
                index = wrap(i);
                render();
            }

            function buildDots() {
                dotsWrap.innerHTML = '';
                for (let i = 0; i < total; i++) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'carousel-dot';
                    dot.setAttribute('role', 'tab');
                    dot.setAttribute('aria-label', 'Go to ' + slides[i].querySelector('h3').textContent);
                    dot.addEventListener('click', () => goTo(i));
                    dotsWrap.appendChild(dot);
                }
            }

            prevBtn.addEventListener('click', () => goTo(index - 1));
            nextBtn.addEventListener('click', () => goTo(index + 1));

            viewport.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    goTo(index - 1);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    goTo(index + 1);
                }
            });

            // Drag / swipe support (mouse + touch)
            let dragging = false;
            let startX = 0;

            function dragStart(x) {
                dragging = true;
                startX = x;
            }

            function dragEnd(x) {
                if (!dragging) return;
                dragging = false;

                const delta = x - startX;
                if (Math.abs(delta) < 40) return;

                if (delta < 0) {
                    goTo(index + 1);
                } else {
                    goTo(index - 1);
                }
            }

            track.addEventListener('mousedown', (e) => dragStart(e.clientX));
            window.addEventListener('mouseup', (e) => dragEnd(e.clientX));

            track.addEventListener('touchstart', (e) => dragStart(e.touches[0].clientX), { passive: true });
            track.addEventListener('touchend', (e) => dragEnd(e.changedTouches[0].clientX), { passive: true });

            buildDots();
            render();
        })();

        // -- Scroll reveal ------------------------------------------------
        (function () {
            const items = document.querySelectorAll('.reveal');
            if (items.length === 0) return;

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReducedMotion) {
                items.forEach(el => el.classList.add('revealed'));
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

            items.forEach(el => observer.observe(el));
        })();

        // -- Back to top --------------------------------------------------
        (function () {
            const btn = document.getElementById('backToTop');
            if (!btn) return;

            let ticking = false;

            function onScroll() {
                if (ticking) return;
                ticking = true;

                requestAnimationFrame(() => {
                    btn.classList.toggle('visible', window.scrollY > 480);
                    ticking = false;
                });
            }

            window.addEventListener('scroll', onScroll, { passive: true });

            btn.addEventListener('click', () => {
                const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
            });
        })();
    </script>

@include('partials.signin-modal')

<script src="{{ asset('js/dtc-app.js') }}" defer></script>
<script src="{{ asset('js/welcome-interactive.js') }}" defer></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (window.lucide) lucide.createIcons();
        });
    </script>
</body>
</html>
