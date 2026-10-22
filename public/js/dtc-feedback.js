/**
 * DTC EMS — Unified Feedback System
 * ─────────────────────────────────────────────────────────────────────────
 * Provides:
 *   1. window.dtcToast  — programmatic toast API
 *   2. data-dtc-confirm — Bootstrap modal confirmation for destructive actions
 *
 * Dependencies: jQuery (for Bootstrap modal), Lucide (already loaded globally)
 * ─────────────────────────────────────────────────────────────────────────
 */

(function (window, document) {
    'use strict';

    // ── 1. TOAST SYSTEM ──────────────────────────────────────────────────

    var ICONS = {
        success : 'check-circle',
        error   : 'x-circle',
        warning : 'alert-triangle',
        info    : 'info',
    };

    var DEFAULT_DURATION = {
        success : 4000,
        info    : 5000,
        warning : 6000,
        error   : 0,     // errors stay until dismissed
    };

    var MAX_VISIBLE = 5;

    /**
     * Ensure the toast container is in the DOM.
     * The Blade component already renders it, but we create it as a fallback
     * so the JS API works on any page even without the component.
     */
    function getContainer() {
        var el = document.getElementById('dtc-toast-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'dtc-toast-container';
            el.setAttribute('role', 'region');
            el.setAttribute('aria-live', 'polite');
            el.setAttribute('aria-label', 'Notifications');
            el.setAttribute('aria-atomic', 'false');
            document.body.appendChild(el);
        }
        return el;
    }

    /**
     * Remove excess toasts when the stack grows too large.
     */
    function pruneStack(container) {
        var toasts = container.querySelectorAll('.dtc-toast:not(.is-leaving)');
        if (toasts.length > MAX_VISIBLE) {
            // Remove the oldest (last in the reversed flex column)
            dismissToast(toasts[toasts.length - 1]);
        }
    }

    /**
     * Animate-out and remove a toast element.
     */
    function dismissToast(el) {
        if (el.classList.contains('is-leaving')) return;
        el.classList.add('is-leaving');
        el.addEventListener('animationend', function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, { once: true });
    }

    /**
     * Build and show a toast.
     *
     * @param {Object} opts
     * @param {string} opts.message   – the text shown
     * @param {string} [opts.type]    – "success" | "error" | "warning" | "info"
     * @param {string} [opts.title]   – optional bold heading
     * @param {number} [opts.duration]– ms before auto-dismiss (0 = manual only)
     */
    function showToast(opts) {
        opts = opts || {};
        var type     = opts.type    || 'info';
        var message  = opts.message || '';
        var title    = opts.title   || '';
        var duration = (opts.duration !== undefined)
                        ? opts.duration
                        : DEFAULT_DURATION[type] || 5000;

        var container = getContainer();
        pruneStack(container);

        // Build toast element
        var toast = document.createElement('div');
        toast.className   = 'dtc-toast is-' + type;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

        var iconName = ICONS[type] || 'info';

        var bodyHTML = '';
        if (title) {
            bodyHTML += '<div class="dtc-toast-title">' + escapeHtml(title) + '</div>';
        }
        bodyHTML += '<div class="dtc-toast-message">' + escapeHtml(message) + '</div>';

        toast.innerHTML = [
            '<div class="dtc-toast-icon">',
            '  <i data-lucide="' + iconName + '" aria-hidden="true"></i>',
            '</div>',
            '<div class="dtc-toast-body">',
            bodyHTML,
            '</div>',
            '<button class="dtc-toast-close" aria-label="Dismiss notification" type="button">',
            '  <i data-lucide="x" aria-hidden="true"></i>',
            '</button>',
            duration > 0
              ? '<div class="dtc-toast-progress" style="animation-duration:' + duration + 'ms"></div>'
              : '',
        ].join('');

        // Close button
        toast.querySelector('.dtc-toast-close').addEventListener('click', function () {
            dismissToast(toast);
        });

        container.prepend(toast);

        // Render lucide icons inside the new toast
        if (window.lucide) {
            lucide.createIcons({ nodes: Array.from(toast.querySelectorAll('[data-lucide]')) });
        }

        // Auto-dismiss
        if (duration > 0) {
            setTimeout(function () { dismissToast(toast); }, duration);
        }

        return toast;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Public API
    window.dtcToast = {
        show   : showToast,
        success: function (msg, opts) { return showToast(Object.assign({ type: 'success', message: msg }, opts)); },
        error  : function (msg, opts) { return showToast(Object.assign({ type: 'error',   message: msg }, opts)); },
        warning: function (msg, opts) { return showToast(Object.assign({ type: 'warning', message: msg }, opts)); },
        info   : function (msg, opts) { return showToast(Object.assign({ type: 'info',    message: msg }, opts)); },
    };


    // ── 2. CONFIRMATION MODAL ─────────────────────────────────────────────

    /**
     * Open the DTC confirmation modal with settings derived from the trigger
     * element's data-attributes.
     *
     * Trigger attributes (on any button, link, or form submit):
     *   data-dtc-confirm
     *   data-dtc-confirm-title    (heading, defaults to "Are you sure?")
     *   data-dtc-confirm-message  (body, defaults to "This action cannot be undone.")
     *   data-dtc-confirm-ok       (confirm button label, defaults to "Confirm")
     *   data-dtc-confirm-cancel   (cancel button label, defaults to "Cancel")
     *   data-dtc-confirm-type     ("danger" | "warning", defaults to "danger")
     *   data-dtc-confirm-form     (CSS selector of <form> to submit on confirm)
     *   data-dtc-confirm-href     (URL to navigate to on confirm)
     */
    var confirmState = { trigger: null, action: null, open: false, underlyingModal: null };
    var confirmModal = document.getElementById('dtcConfirmModal');

    function closeConfirmModal() {
        if (!confirmModal || !confirmState.open) return;
        confirmModal.classList.remove('show');
        confirmModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('dtc-confirm-open');
        var backdrop = document.querySelector('.dtc-confirm-backdrop');
        if (backdrop) backdrop.remove();
        confirmState.open = false;
        var trigger = confirmState.trigger;
        var underlyingModal = confirmState.underlyingModal;
        confirmState.action = null;
        confirmState.underlyingModal = null;
        window.setTimeout(function () {
            confirmModal.style.display = 'none';
            if (trigger && typeof trigger.focus === 'function') trigger.focus();
            if (underlyingModal && window.jQuery) {
                var bootstrapModal = window.jQuery(underlyingModal).data('bs.modal');
                if (bootstrapModal && typeof bootstrapModal._enforceFocus === 'function') bootstrapModal._enforceFocus();
            }
        }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 180);
    }

    function renderConfirmMessage(message, subject) {
        var messageEl = confirmModal.querySelector('#dtcConfirmMessage');
        messageEl.textContent = message;
        if (subject) {
            messageEl.appendChild(document.createTextNode(' '));
            var strong = document.createElement('strong');
            strong.textContent = subject;
            messageEl.appendChild(strong);
        }
    }

    function openConfirmModal(options) {
        if (!confirmModal) return false;
        options = options || {};
        var tone = ['success', 'danger', 'neutral'].indexOf(options.tone) >= 0 ? options.tone : 'neutral';
        var iconNames = { success: 'user-check', danger: 'x-circle', neutral: 'help-circle' };
        confirmState.trigger = options.trigger || document.activeElement;
        confirmState.underlyingModal = confirmState.trigger && confirmState.trigger.closest ? confirmState.trigger.closest('.modal.show') : null;
        confirmState.action = options.onConfirm || null;
        confirmState.open = true;

        confirmModal.className = 'modal dtc-confirm-modal is-' + tone;
        confirmModal.querySelector('#dtcConfirmTitle').textContent = options.title || 'Confirm action?';
        renderConfirmMessage(options.message || 'Please confirm that you want to continue.', options.subject || '');
        var ok = confirmModal.querySelector('#dtcConfirmOk');
        var cancel = confirmModal.querySelector('#dtcConfirmCancel');
        ok.textContent = options.okLabel || 'Confirm';
        ok.disabled = false;
        cancel.textContent = options.cancelLabel || 'Cancel';
        var icon = confirmModal.querySelector('#dtcConfirmIcon');
        icon.setAttribute('data-lucide', options.icon || iconNames[tone]);
        if (window.lucide) window.lucide.createIcons({ nodes: [icon] });

        confirmModal.style.display = 'block';
        confirmModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('dtc-confirm-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'dtc-confirm-backdrop';
        backdrop.addEventListener('click', closeConfirmModal);
        document.body.appendChild(backdrop);
        if (window.jQuery) window.jQuery(document).off('focusin.bs.modal');
        window.requestAnimationFrame(function () { confirmModal.classList.add('show'); });
        (tone === 'danger' ? cancel : ok).focus();
        return true;
    }

    window.dtcConfirm = { open: openConfirmModal, close: closeConfirmModal };

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-dtc-confirm]');
        if (!trigger) return;
        var formSel = trigger.dataset.dtcConfirmForm;
        var form = formSel ? document.querySelector(formSel) : trigger.form;
        if (form && !form.checkValidity()) {
            form.reportValidity();
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var legacyType = trigger.dataset.dtcConfirmType;
        var tone = trigger.dataset.dtcConfirmTone || (legacyType === 'success' ? 'success' : (legacyType === 'danger' ? 'danger' : 'neutral'));
        openConfirmModal({
            trigger: trigger,
            tone: tone,
            title: trigger.dataset.dtcConfirmTitle,
            message: trigger.dataset.dtcConfirmMessage,
            subject: trigger.dataset.dtcConfirmSubject,
            okLabel: trigger.dataset.dtcConfirmOk,
            cancelLabel: trigger.dataset.dtcConfirmCancel,
            icon: trigger.dataset.dtcConfirmIcon,
            onConfirm: function () {
                if (form) {
                    form.dataset.confirmed = 'true';
                    if (typeof form.requestSubmit === 'function') form.requestSubmit();
                    else form.submit();
                } else if (trigger.dataset.dtcConfirmHref) {
                    window.location.href = trigger.dataset.dtcConfirmHref;
                }
            }
        });
    }, true);

    if (confirmModal) {
        confirmModal.querySelector('#dtcConfirmClose').addEventListener('click', closeConfirmModal);
        confirmModal.querySelector('#dtcConfirmCancel').addEventListener('click', closeConfirmModal);
        confirmModal.querySelector('#dtcConfirmOk').addEventListener('click', function () {
            if (this.disabled) return;
            this.disabled = true;
            var action = confirmState.action;
            closeConfirmModal();
            if (action) action();
        });
        confirmModal.addEventListener('click', function (event) {
            if (event.target === confirmModal) closeConfirmModal();
        });
        document.addEventListener('keydown', function (event) {
            if (!confirmState.open) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeConfirmModal();
                return;
            }
            if (event.key !== 'Tab') return;
            var focusable = Array.prototype.slice.call(confirmModal.querySelectorAll('button:not([disabled])'));
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    }


    // ── 3. AXIOS / FETCH RESPONSE HELPER ─────────────────────────────────
    /**
     * window.dtcHandleAjaxError(error)
     * Show a user-friendly toast for Axios / fetch errors.
     * Works with Laravel's standard JSON error responses.
     *
     * Usage in Axios catch:
     *   .catch(function(err) { window.dtcHandleAjaxError(err); });
     */
    window.dtcHandleAjaxError = function (error) {
        var response = error && error.response;
        if (!response) {
            window.dtcToast.error('A network error occurred. Please check your connection.');
            return;
        }

        var status = response.status;
        var data   = response.data || {};

        if (status === 422) {
            // Validation — collect first message from Laravel's error bag
            var errors = data.errors || {};
            var keys   = Object.keys(errors);
            var first  = keys.length ? errors[keys[0]][0] : (data.message || 'Please correct the form errors.');
            window.dtcToast.warning(first);
        } else if (status === 401) {
            window.dtcToast.error('Your session has expired. Please log in again.');
        } else if (status === 403) {
            window.dtcToast.error('You do not have permission to perform this action.');
        } else if (status === 404) {
            window.dtcToast.error('The requested resource was not found.');
        } else {
            var msg = (data && data.message) || 'Something went wrong. Please try again.';
            // Never expose stack traces
            if (msg.length > 200 || msg.indexOf('\n') !== -1) {
                msg = 'Something went wrong. Please try again.';
            }
            window.dtcToast.error(msg);
        }
    };

})(window, document);
