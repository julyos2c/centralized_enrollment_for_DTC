/**
 * DTC EMS — Sign In Modal
 * ---------------------------------------------------------------
 * Self-contained. No jQuery required.
 * Depends on: signin-modal.css, the #dtcSignInModal element.
 *
 * Triggers: any element with  data-signin-modal="trigger"
 * Close:    backdrop click, × button (data-signin-modal="close"),
 *           or Escape key
 */

(function () {
    'use strict';

    const MODAL_ID   = 'dtcSignInModal';
    const CLOSE_MS   = 200; // must match CSS dtcModalOut duration

    let modal, box, firstFocusable, lastFocusable;
    let previouslyFocused = null;
    let closeTimer = null;

    /* ── Init (safe to call before DOMContentLoaded via defer) ── */
    function init() {
        modal = document.getElementById(MODAL_ID);
        if (!modal) return;

        box = modal.querySelector('.dtc-modal-box');

        /* Delegate all data-signin-modal clicks */
        document.addEventListener('click', handleClick);

        /* Keyboard: Escape + focus trap */
        document.addEventListener('keydown', handleKeydown);

        /* Password toggle */
        modal.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-signin-modal="toggle-pw"]');
            if (!btn) return;
            const wrap = btn.closest('.dtc-modal-input-wrap');
            const input = wrap && wrap.querySelector('.dtc-modal-input');
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', isPassword ? 'eye' : 'eye-off');
                if (window.lucide) lucide.createIcons({ nodes: [icon] });
            }
            btn.setAttribute('aria-label',
                isPassword ? 'Hide password' : 'Show password'
            );
        });

        /* If the page was redirected back with errors (e.g. wrong password),
           reopen the modal automatically so the user sees the inline error. */
        if (modal.querySelector('.dtc-modal-error')) {
            openModal();
        }
    }

    /* ── Click handler ───────────────────────────────────────── */
    function handleClick(e) {
        const el = e.target.closest('[data-signin-modal]');
        if (!el) return;

        const action = el.getAttribute('data-signin-modal');

        if (action === 'trigger') {
            e.preventDefault();
            openModal();
        } else if (action === 'close') {
            closeModal();
        }
    }

    /* ── Keyboard handler ────────────────────────────────────── */
    function handleKeydown(e) {
        if (!modal || modal.hidden) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal();
            return;
        }

        /* Focus trap */
        if (e.key === 'Tab') {
            const focusable = getFocusable();
            if (!focusable.length) return;
            const first = focusable[0];
            const last  = focusable[focusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }
    }

    /* ── Open ────────────────────────────────────────────────── */
    function openModal() {
        if (!modal) return;
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
            box.classList.remove('is-closing');
        }

        previouslyFocused = document.activeElement;

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        document.body.setAttribute('aria-hidden', 'false');

        /* Focus first interactive element inside the modal */
        requestAnimationFrame(function () {
            const focusable = getFocusable();
            if (focusable.length) focusable[0].focus();
        });
    }

    /* ── Close ───────────────────────────────────────────────── */
    function closeModal() {
        if (!modal || modal.hidden) return;

        box.classList.add('is-closing');

        closeTimer = setTimeout(function () {
            modal.hidden = true;
            box.classList.remove('is-closing');
            document.body.style.overflow = '';
            closeTimer = null;

            /* Restore focus to the element that opened the modal */
            if (previouslyFocused && previouslyFocused.focus) {
                previouslyFocused.focus();
            }
        }, CLOSE_MS);
    }

    /* ── Focus helpers ───────────────────────────────────────── */
    function getFocusable() {
        if (!modal) return [];
        return Array.from(
            modal.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), ' +
                'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )
        ).filter(function (el) {
            return !el.closest('[hidden]') && el.offsetParent !== null;
        });
    }

    /* ── Public API (optional) ───────────────────────────────── */
    window.DtcSignInModal = { open: openModal, close: closeModal };

    /* ── Boot ────────────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
