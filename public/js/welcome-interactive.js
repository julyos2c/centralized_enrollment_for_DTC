/**
 * DTC EMS — Welcome Page Interactive
 * ─────────────────────────────────────────────────────────────────
 * 1. Clickable service carousel cards → detail modal
 * 2. Enriched FAQ accordion with bullet lists + sub-details
 *
 * No jQuery. No external deps beyond Lucide (already on page).
 * Follows the same pattern as signin-modal.js.
 */

(function () {
    'use strict';

    /* ================================================================
       SERVICE CARD DATA
       Each card's `id` must match data-service-id on the .service-card
       element in the Blade template.
    ================================================================ */
    const SERVICES = {
        enrollment: {
            icon: 'graduation-cap',
            badge: 'Enrollment',
            title: 'Online Enrollment',
            tagline: 'Enroll anytime, anywhere — no long queues.',
            description:
                'The DTC EMS Online Enrollment module lets students complete their entire enrollment process digitally. From subject selection to section assignment, everything is handled in one place — no need to line up at the Registrar\'s Office.',
            highlights: [
                { icon: 'list-checks',  text: 'Step-by-step guided enrollment wizard' },
                { icon: 'calendar',     text: 'Real-time subject & section availability' },
                { icon: 'bell',         text: 'Instant notifications on enrollment status' },
                { icon: 'file-text',    text: 'Digital enrollment form — no paper required' },
                { icon: 'refresh-cw',   text: 'Add/drop subjects within the allowed period' },
                { icon: 'shield-check', text: 'Registrar approval workflow with audit trail' },
            ],
            who: 'Students, New Applicants, and Returning Enrollees',
        },
        certificates: {
            icon: 'file-text',
            badge: 'Certificates',
            title: 'Certificate Requests',
            tagline: 'Request official school records in just a few clicks.',
            description:
                'Need a transcript, diploma, or any official certification? Submit your document request online, track its status in real time, and get notified the moment it\'s ready for release — all without visiting the office.',
            highlights: [
                { icon: 'file-plus',        text: 'Request transcripts, diplomas, and certifications online' },
                { icon: 'clock',            text: 'Track request status from submission to release in real time' },
                { icon: 'bell',             text: 'Email and in-app notifications at every stage' },
                { icon: 'user-check',       text: 'Registrar review and approval before processing' },
                { icon: 'archive',          text: 'Full digital history of all your past requests' },
                { icon: 'shield-check',     text: 'Only authorised staff can access and process your records' },
            ],
            who: 'Current Students, Alumni, and Graduates',
        },
        appointments: {
            icon: 'calendar-check',
            badge: 'Appointments',
            title: 'Appointment Scheduling',
            tagline: 'Book a pickup or claiming schedule that works for you.',
            description:
                'No more guessing when to show up. The Appointment Scheduling module lets you choose a specific date and time to claim your requested documents, so you can plan your visit and skip unnecessary waiting.',
            highlights: [
                { icon: 'calendar',         text: 'Browse available slots and book your preferred date' },
                { icon: 'clock',            text: 'Choose a time window that fits your schedule' },
                { icon: 'bell',             text: 'Reminder notifications before your appointment date' },
                { icon: 'refresh-cw',       text: 'Reschedule or cancel appointments if plans change' },
                { icon: 'map-pin',          text: 'Clear pick-up location details included in confirmation' },
                { icon: 'check-circle-2',   text: 'Appointment linked directly to your document request' },
            ],
            who: 'Students and Alumni with pending document requests',
        },
        payments: {
            icon: 'banknote',
            badge: 'Payments',
            title: 'Payment Processing',
            tagline: 'Pay online via GCash, or walk in and pay at the Cashier.',
            description:
                'The Payment Processing module handles all tuition and fee transactions — from automatic fee computation to GCash QR payments. The Cashier verifies each payment and issues an official digital receipt, keeping every transaction transparent and auditable.',
            highlights: [
                { icon: 'calculator',       text: 'Automatic fee computation based on enrolled units' },
                { icon: 'qr-code',          text: 'GCash QR payment — scan, pay, then upload your screenshot' },
                { icon: 'banknote',         text: 'Walk-in cash payment accepted directly at the Cashier window' },
                { icon: 'receipt',          text: 'Official digital receipt issued by the Cashier after verification' },
                { icon: 'history',          text: 'Complete payment history with reference numbers' },
                { icon: 'lock',             text: 'Enrollment is finalised only after payment is confirmed' },
            ],
            who: 'Students, Cashier Staff, and Accounting',
        },
        access: {
            icon: 'shield',
            badge: 'Access',
            title: 'Role-Based Access',
            tagline: 'Every user gets the right tools — nothing more, nothing less.',
            description:
                'The DTC EMS uses a secure role-based access system. Each user type — student, alumni, registrar, cashier, or admin — sees only the features and data relevant to their role, keeping the system organised and your information protected.',
            highlights: [
                { icon: 'graduation-cap',   text: 'Students — enroll, pay fees, and request documents' },
                { icon: 'user',             text: 'Alumni — request certifications and book appointments' },
                { icon: 'clipboard-list',   text: 'Registrar — manage applications, records, and document requests' },
                { icon: 'banknote',         text: 'Cashier — verify payments and issue official receipts' },
                { icon: 'shield',           text: 'Admin — full system access for configuration and user management' },
                { icon: 'eye-off',          text: 'No user can access another role\'s data or functions' },
            ],
            who: 'All DTC EMS users — students, staff, and administrators',
        },
    };

    /* ================================================================
       MODAL ELEMENTS (injected once into <body>)
    ================================================================ */
    const MODAL_ID = 'dtcServiceModal';

    function buildModal() {
        if (document.getElementById(MODAL_ID)) return;

        const el = document.createElement('div');
        el.id = MODAL_ID;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-labelledby', 'dtcServiceModalTitle');
        el.setAttribute('hidden', '');
        el.className = 'dtc-svc-overlay';
        el.innerHTML = `
            <div class="dtc-svc-backdrop" data-svc-modal="close" aria-hidden="true"></div>
            <div class="dtc-svc-box">
                <button class="dtc-svc-close" data-svc-modal="close" aria-label="Close">
                    <i data-lucide="x"></i>
                </button>

                <div class="dtc-svc-header">
                    <div class="dtc-svc-icon-wrap" id="dtcSvcIcon">
                        <i data-lucide="star"></i>
                    </div>
                    <div>
                        <span class="dtc-svc-badge" id="dtcSvcBadge">Service</span>
                        <h2 class="dtc-svc-title" id="dtcServiceModalTitle">Service Name</h2>
                        <p class="dtc-svc-tagline" id="dtcSvcTagline"></p>
                    </div>
                </div>

                <p class="dtc-svc-desc" id="dtcSvcDesc"></p>

                <div class="dtc-svc-highlights-label">
                    <i data-lucide="check-circle-2"></i> Key Features
                </div>
                <ul class="dtc-svc-highlights" id="dtcSvcHighlights"></ul>

                <div class="dtc-svc-who">
                    <i data-lucide="users"></i>
                    <span id="dtcSvcWho"></span>
                </div>
            </div>
        `;
        document.body.appendChild(el);

        /* Delegated close events */
        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-svc-modal="close"]')) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !document.getElementById(MODAL_ID).hidden) closeModal();
        });
    }

    /* ================================================================
       OPEN / CLOSE
    ================================================================ */
    let _prevFocus = null;
    let _closeTimer = null;

    function openModal(serviceId) {
        const data = SERVICES[serviceId];
        if (!data) return;

        const modal = document.getElementById(MODAL_ID);
        const box   = modal.querySelector('.dtc-svc-box');

        /* Populate */
        /* Lucide replaces <i data-lucide> with <svg>, so querying the original
           <i> after first open returns null. Re-inject a fresh <i> each time
           and let createIcons() below convert it. */
        const iconWrap = modal.querySelector('#dtcSvcIcon');
        iconWrap.innerHTML = `<i data-lucide="${data.icon}"></i>`;

        modal.querySelector('#dtcSvcBadge').textContent   = data.badge;
        modal.querySelector('#dtcServiceModalTitle').textContent = data.title;
        modal.querySelector('#dtcSvcTagline').textContent  = data.tagline;
        modal.querySelector('#dtcSvcDesc').textContent     = data.description;
        modal.querySelector('#dtcSvcWho').textContent      = data.who;

        const ul = modal.querySelector('#dtcSvcHighlights');
        ul.innerHTML = data.highlights.map(h => `
            <li class="dtc-svc-highlight-item">
                <span class="dtc-svc-hi-icon"><i data-lucide="${h.icon}"></i></span>
                <span>${h.text}</span>
            </li>
        `).join('');

        /* Refresh Lucide icons injected via innerHTML */
        if (window.lucide) lucide.createIcons({ nodes: modal.querySelectorAll('[data-lucide]') });

        /* Show */
        clearTimeout(_closeTimer);
        box.classList.remove('is-closing');
        modal.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';

        _prevFocus = document.activeElement;
        const firstBtn = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstBtn) firstBtn.focus();
    }

    function closeModal() {
        const modal = document.getElementById(MODAL_ID);
        if (!modal || modal.hidden) return;
        const box = modal.querySelector('.dtc-svc-box');
        box.classList.add('is-closing');
        _closeTimer = setTimeout(function () {
            modal.setAttribute('hidden', '');
            box.classList.remove('is-closing');
            document.body.style.overflow = '';
            if (_prevFocus) _prevFocus.focus();
        }, 220);
    }

    /* ================================================================
       WIRE UP CAROUSEL CARDS
       Cards that are in the center position (.is-center) are clickable.
       Side cards advance the carousel first, then open on second click.
       We also make ALL cards keyboard-accessible.
    ================================================================ */
    function wireCards() {
        document.addEventListener('click', function (e) {
            const card = e.target.closest('.service-card[data-service-id]');
            if (!card) return;

            /* Only open modal when card is the center (active) card */
            if (card.classList.contains('is-center')) {
                openModal(card.dataset.serviceId);
            }
            /* Side cards: let the existing carousel JS handle the rotation
               naturally — no extra logic needed here. */
        });

        /* Keyboard: Enter / Space on center card → open modal */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const card = document.activeElement && document.activeElement.closest('.service-card[data-service-id]');
            if (!card || !card.classList.contains('is-center')) return;
            e.preventDefault();
            openModal(card.dataset.serviceId);
        });

        /* Make cards focusable and show pointer cursor on center card */
        document.querySelectorAll('.service-card[data-service-id]').forEach(function (card) {
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'button');
        });

        /* Update aria labels when carousel rotates (MutationObserver on class) */
        const track = document.querySelector('.carousel-track');
        if (!track) return;

        const obs = new MutationObserver(function () {
            track.querySelectorAll('.service-card[data-service-id]').forEach(function (card) {
                const isCenter = card.classList.contains('is-center');
                const serviceId = card.dataset.serviceId;
                const data = SERVICES[serviceId];
                if (!data) return;
                card.setAttribute('aria-label',
                    isCenter
                        ? `${data.title} — press Enter to learn more`
                        : `${data.title} — click to navigate`
                );
                card.style.cursor = isCenter ? 'pointer' : 'grab';
            });
        });
        obs.observe(track, { attributeFilter: ['class'], subtree: true });

        /* Trigger once on load */
        obs.takeRecords();
        track.querySelectorAll('.service-card[data-service-id]').forEach(function (card) {
            const isCenter = card.classList.contains('is-center');
            const data = SERVICES[card.dataset.serviceId];
            if (!data) return;
            card.setAttribute('aria-label',
                isCenter
                    ? `${data.title} — press Enter to learn more`
                    : `${data.title} — click to navigate`
            );
            card.style.cursor = isCenter ? 'pointer' : 'grab';
        });
    }

    /* ================================================================
       FAQ ENRICHMENT
       Replaces plain <p> inside .faq-answer with a structured list
       when the answer element has data-faq-bullets attribute.
       Falls back gracefully if data attribute is absent.
    ================================================================ */
    const FAQ_DATA = [
        {
            q: 'How do I enroll through DTC EMS?',
            answer: 'The online enrollment process is simple and fully guided:',
            bullets: [
                { icon: 'log-in',          text: '<strong>Sign in</strong> to the portal using your registered student email and password' },
                { icon: 'list-checks',     text: '<strong>Go to Enrollment</strong> — select your program, year level, and semester' },
                { icon: 'book-open',       text: '<strong>Choose your subjects</strong> from the available offerings (load limit is checked automatically)' },
                { icon: 'send',            text: '<strong>Submit</strong> your enrollment form for Registrar review and approval' },
                { icon: 'credit-card',     text: '<strong>Pay your fees</strong> via GCash QR or walk-in at the Cashier, then upload your screenshot' },
                { icon: 'check-circle-2',  text: '<strong>Wait for confirmation</strong> — you\'ll be notified once enrollment is finalised' },
            ],
        },
        {
            q: 'How do I request a certificate?',
            answer: 'Requesting official documents is fast and fully online:',
            bullets: [
                { icon: 'layout-dashboard', text: '<strong>Go to Certificate Requests</strong> from your student dashboard' },
                { icon: 'mouse-pointer-click', text: '<strong>Choose the document</strong> you need — transcript, diploma, certifications, and more' },
                { icon: 'send',            text: '<strong>Submit your request</strong> — the Registrar will review and process it' },
                { icon: 'calendar',        text: '<strong>Book an appointment</strong> to pick a date and time for claiming your document' },
                { icon: 'bell',            text: '<strong>Track your request</strong> and receive in-app notifications at every stage' },
                { icon: 'map-pin',         text: '<strong>Pick up in person</strong> at the Registrar\'s Office on your chosen appointment date' },
            ],
        },
        {
            q: 'How does appointment scheduling work?',
            answer: 'Appointment scheduling lets you choose exactly when to visit — no guessing, no waiting:',
            bullets: [
                { icon: 'file-check',      text: '<strong>After your request is approved</strong>, an appointment booking option becomes available' },
                { icon: 'calendar',        text: '<strong>Browse available slots</strong> and pick a date and time window that fits your schedule' },
                { icon: 'bell',            text: '<strong>Receive a confirmation</strong> with your appointment details via in-app notification' },
                { icon: 'refresh-cw',      text: '<strong>Reschedule or cancel</strong> your appointment if your plans change before the date' },
                { icon: 'map-pin',         text: '<strong>Show up on time</strong> at the Registrar\'s Office — your slot is reserved for you' },
                { icon: 'check-circle-2',  text: '<strong>Claim your document</strong> and the request is marked complete in the system' },
            ],
        },
        {
            q: 'What payment options are available?',
            answer: 'The DTC EMS supports two payment methods — online and in person:',
            bullets: [
                { icon: 'qr-code',         text: '<strong>GCash QR (Online)</strong> — scan the official DTC GCash QR code, complete the payment, then upload your screenshot as proof' },
                { icon: 'banknote',        text: '<strong>Walk-in Cash Payment</strong> — visit the Cashier\'s Office in person and pay your fees directly at the counter' },
                { icon: 'receipt',         text: '<strong>Official receipt</strong> is issued by the Cashier after verifying your payment (online or walk-in)' },
                { icon: 'history',         text: '<strong>All transactions</strong> are recorded in the system with reference numbers for a full audit trail' },
                { icon: 'alert-circle',    text: '<strong>Important:</strong> Enrollment is fully confirmed only after the Cashier has verified your payment' },
            ],
            note: 'Additional payment channels may be added in future system updates.',
        },
        {
            q: 'I forgot my password. What do I do?',
            answer: 'Recovering your account is quick — follow these steps:',
            bullets: [
                { icon: 'mouse-pointer-click', text: '<strong>Step 1 — Click “Sign In”</strong> then find the Forgot Password link below the password field' },
                { icon: 'mail',            text: '<strong>Step 2 — Enter your registered email</strong> address and submit the recovery form' },
                { icon: 'inbox',           text: '<strong>Step 3 — Check your inbox</strong> for a password reset link (also check your spam or junk folder)' },
                { icon: 'key-round',       text: '<strong>Step 4 — Click the link</strong> in the email and set a new strong password' },
                { icon: 'log-in',          text: '<strong>Step 5 — Sign back in</strong> with your new password — all your data remains fully intact' },
                { icon: 'phone',           text: '<strong>Still locked out?</strong> Contact the Registrar\'s Office for manual account recovery assistance' },
            ],
        },
    ];

    /* Keyword lookup — matched against the actual Blade .faq-question text.
       Order matches FAQ_DATA above; keywords are substrings of the real questions. */
    const FAQ_KEYWORDS = [
        'enroll through',
        'request a certificate',
        'appointment scheduling',
        'payment options',
        'forgot my password',
    ];

    function enrichFaqs() {
        const faqItems = document.querySelectorAll('.faq-item');
        if (!faqItems.length) return;

        faqItems.forEach(function (item) {
            const questionEl = item.querySelector('.faq-question');
            const answerEl   = item.querySelector('.faq-answer');
            if (!questionEl || !answerEl) return;

            const questionText = questionEl.textContent.toLowerCase();

            /* Find the FAQ_DATA entry whose keyword appears in this question */
            const data = FAQ_DATA.find(function (entry, i) {
                return questionText.includes(FAQ_KEYWORDS[i]);
            });
            if (!data) return;

            /* Build rich HTML */
            let html = `<p class="dtc-faq-intro">${data.answer}</p>`;
            html += `<ul class="dtc-faq-bullets">`;
            data.bullets.forEach(function (b) {
                html += `
                    <li class="dtc-faq-bullet">
                        <span class="dtc-faq-bullet-icon"><i data-lucide="${b.icon}"></i></span>
                        <span>${b.text}</span>
                    </li>`;
            });
            html += `</ul>`;
            if (data.note) {
                html += `<p class="dtc-faq-note"><i data-lucide="info"></i> ${data.note}</p>`;
            }

            answerEl.innerHTML = html;
        });

        /* ── Accordion: one-at-a-time + dynamic maxHeight ──────────────
           Strategy: use a CAPTURE-phase listener so we run BEFORE the
           existing inline accordion script on the page. We close all
           siblings here; the original script then toggles the clicked
           item as usual. A MutationObserver watches class changes on
           every .faq-item and keeps maxHeight in sync with .active.
        ────────────────────────────────────────────────────────────── */

        /* 1. MutationObserver — fix maxHeight whenever .active changes */
        document.querySelectorAll('.faq-item').forEach(function (item) {
            const answer = item.querySelector('.faq-answer');
            if (!answer) return;

            new MutationObserver(function () {
                if (item.classList.contains('active')) {
                    /* measure after paint so scrollHeight is accurate */
                    requestAnimationFrame(function () {
                        answer.style.maxHeight = answer.scrollHeight + 'px';
                    });
                } else {
                    answer.style.maxHeight = '0';
                }
            }).observe(item, { attributeFilter: ['class'] });
        });

        /* 2. Capture-phase click — close all siblings BEFORE the
              original bubble-phase handler toggles the clicked item */
        document.querySelector('.faq-list') &&
        document.querySelector('.faq-list').addEventListener('click', function (e) {
            const btn = e.target.closest('.faq-question');
            if (!btn) return;

            const clickedItem = btn.closest('.faq-item');

            /* Close every OTHER open item (leave clicked item alone —
               the original handler will toggle it correctly) */
            document.querySelectorAll('.faq-item.active').forEach(function (openItem) {
                if (openItem === clickedItem) return;
                openItem.classList.remove('active');
            });
        }, true /* capture = true → runs before bubble-phase handlers */);

        /* Close open FAQ when clicking anywhere outside a .faq-item */
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.faq-item')) {
                document.querySelectorAll('.faq-item.active').forEach(function (item) {
                    item.classList.remove('active');
                });
            }
        });

        /* Render Lucide icons injected into FAQ answers */
        if (window.lucide) lucide.createIcons();
    }

    /* ================================================================
       BOOT
    ================================================================ */
    function boot() {
        buildModal();
        wireCards();
        enrichFaqs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();