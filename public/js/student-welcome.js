(function () {
    'use strict';
    const root = document.getElementById('studentWelcome');
    if (!root) return;
    // Escape the navbar's stacking context so the backdrop covers the whole page.
    document.body.appendChild(root);
    const dialog = root.querySelector('[role="dialog"]');
    const open = document.getElementById('studentWelcomeOpen');
    const key = 'dtc-student-welcome:' + root.dataset.user;
    const token = window.crypto.randomUUID ? window.crypto.randomUUID() :
        '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, function (c) {
            return (Number(c) ^ window.crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> Number(c) / 4).toString(16);
        });
    const dismissed = new Set();
    const celebrated = new Set();
    let current = null;
    let busy = false;
    let checking = false;
    let previousFocus = null;
    let confetti = null;
    let confettiTimer = null;
    let channel;
    try { channel = new BroadcastChannel(key); } catch (error) { /* Storage events remain available. */ }

    async function request(action) {
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (!csrf) throw new Error('Missing CSRF token');
        const controller = new AbortController();
        const timer = window.setTimeout(function () { controller.abort(); }, 10000);
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf.content, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: action, token: token })
            });
            if (!response.ok || response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('Welcome acknowledgement unavailable');
            }
            return await response.json();
        } finally { window.clearTimeout(timer); }
    }

    function removeConfetti() {
        window.clearTimeout(confettiTimer);
        if (confetti) confetti.remove();
        confetti = null;
    }
    function celebrate(id) {
        if (celebrated.has(id)) return;
        celebrated.add(id);
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        confetti = document.createElement('div');
        confetti.className = 'student-welcome-confetti';
        confetti.setAttribute('aria-hidden', 'true');
        const count = window.matchMedia('(max-width: 599px)').matches ? 16 : 28;
        for (let i = 0; i < count; i++) {
            const piece = document.createElement('span');
            piece.style.left = (Math.random() * 100) + '%';
            piece.style.width = (8 + Math.random() * 2) + 'px';
            piece.style.height = (i % 4 === 0 ? 9 : 14 + Math.random() * 4) + 'px';
            piece.style.borderRadius = i % 4 === 0 ? '50%' : '0';
            piece.style.animationDelay = (-Math.random() * .7) + 's';
            confetti.appendChild(piece);
        }
        document.body.appendChild(confetti);
        confettiTimer = window.setTimeout(removeConfetti, 6000);
    }
    function close() {
        root.hidden = true;
        current = null;
        removeConfetti();
        if (previousFocus && previousFocus.isConnected) previousFocus.focus();
        previousFocus = null;
    }
    function sync(message) {
        if (!message || message.type !== 'acknowledged' || !Number.isInteger(message.id)) return;
        dismissed.add(message.id);
        if (current === message.id) close();
    }
    if (channel) channel.onmessage = function (event) { sync(event.data); };
    window.addEventListener('storage', function (event) {
        if (event.key !== key || !event.newValue) return;
        try { sync(JSON.parse(event.newValue)); } catch (error) { /* Ignore unrelated malformed data. */ }
    });
    async function receive(event) {
        if (busy || checking) return;
        if (!event) { if (current !== null) close(); return; }
        if (!Number.isInteger(event.id) || dismissed.has(event.id) || document.hidden) return;
        if (current === event.id) { if (event.lease_owned === false) close(); return; }
        if (event.available === false) return;
        // Let payment and confirmation dialogs finish before presenting the welcome.
        if (root.hidden && document.querySelector('.modal.show, .modal.in, dialog[open]')) return;
        checking = true;
        try {
            const result = await request('claim');
            if (!result.accepted) { if (current !== null) close(); return; }
            if (document.hidden || dismissed.has(event.id)) {
                await request('release');
                return;
            }
            if (current === event.id) return;
            previousFocus = document.activeElement;
            current = event.id;
            root.hidden = false;
            open.focus();
            celebrate(event.id);
        } catch (error) { /* A later existing poll can try again; never navigate on failure. */ }
        finally { checking = false; }
    }
    async function acknowledge() {
        if (busy || current === null) return;
        busy = true;
        const id = current;
        open.disabled = true;
        const oldError = root.querySelector('[role="alert"]');
        if (oldError) oldError.remove();
        try {
            const result = await request('acknowledge');
            if (!result.accepted) { close(); return; }
            const message = { type: 'acknowledged', id: id };
            sync(message);
            if (channel) channel.postMessage(message);
            try { localStorage.setItem(key, JSON.stringify(message)); } catch (error) { /* Server state still prevents replay. */ }
            if (root.dataset.redirectAfterAck === '1') window.location.assign(root.dataset.dashboardUrl);
        } catch (error) {
            const notice = document.createElement('p');
            notice.setAttribute('role', 'alert');
            notice.textContent = 'Unable to save your choice. Please try again.';
            dialog.appendChild(notice);
        } finally {
            busy = false;
            open.disabled = false;
        }
    }
    open.addEventListener('click', function () { acknowledge(); });
    root.addEventListener('click', function (event) {
        if (event.target === root) acknowledge();
    });
    document.addEventListener('keydown', function (event) {
        if (root.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); acknowledge(); }
        if (event.key === 'Tab') {
            event.preventDefault();
            (busy ? dialog : open).focus();
        }
    }, true);
    document.addEventListener('focusin', function (event) {
        if (!root.hidden && !root.contains(event.target)) (busy ? dialog : open).focus();
    });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && current !== null && !busy) {
            close();
            request('release').catch(function () {});
        }
    });
    window.addEventListener('pagehide', close);
    window.dtcStudentWelcome = {
        receive: receive,
        pollQuery: function () { return current !== null && !document.hidden ? '&welcome_token=' + encodeURIComponent(token) : ''; }
    };
})();
