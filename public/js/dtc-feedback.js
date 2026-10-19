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
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-dtc-confirm]');
        if (!trigger) return;

        // Don't double-fire from forms that also have data-dtc-confirm on a submit button
        // (the form's submit event is handled separately when needed)

        var modal = document.getElementById('dtcConfirmModal');
        if (!modal) return;

        e.preventDefault();
        e.stopPropagation();

        var type    = trigger.dataset.dtcConfirmType    || 'danger';
        var title   = trigger.dataset.dtcConfirmTitle   || 'Are you sure?';
        var message = trigger.dataset.dtcConfirmMessage || 'This action cannot be undone.';
        var okLabel = trigger.dataset.dtcConfirmOk      || 'Confirm';
        var cancelLabel = trigger.dataset.dtcConfirmCancel || 'Cancel';
        var formSel = trigger.dataset.dtcConfirmForm;
        var href    = trigger.dataset.dtcConfirmHref;

        // Update modal content
        modal.className = 'modal fade dtc-confirm-modal is-' + type;

        var iconName = type === 'warning' ? 'alert-triangle' : 'alert-circle';
        var iconEl   = modal.querySelector('#dtcConfirmIcon');
        if (iconEl) {
            iconEl.setAttribute('data-lucide', iconName);
            if (window.lucide) lucide.createIcons({ nodes: [iconEl] });
        }

        var titleEl = modal.querySelector('#dtcConfirmTitle');
        if (titleEl) titleEl.textContent = title;

        var msgEl = modal.querySelector('#dtcConfirmMessage');
        if (msgEl) msgEl.textContent = message;

        var okBtn = modal.querySelector('#dtcConfirmOk');
        if (okBtn) okBtn.textContent = okLabel;

        var cancelBtn = modal.querySelector('#dtcConfirmCancel');
        if (cancelBtn) cancelBtn.textContent = cancelLabel;

        // Wire the OK button
        var newOkBtn = okBtn.cloneNode(true); // remove old listeners
        okBtn.parentNode.replaceChild(newOkBtn, okBtn);

        newOkBtn.textContent = okLabel;
        newOkBtn.addEventListener('click', function () {
            // Close modal first
            if (window.jQuery) {
                jQuery('#dtcConfirmModal').modal('hide');
            }

            if (formSel) {
                var form = document.querySelector(formSel);
                if (form) {
                    // Mark as confirmed so native delete-form listener doesn't re-confirm
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            } else if (href) {
                window.location.href = href;
            } else if (trigger.form) {
                trigger.form.dataset.confirmed = 'true';
                trigger.form.submit();
            }
        });

        // Show modal (Bootstrap jQuery API)
        if (window.jQuery) {
            jQuery('#dtcConfirmModal').modal('show');
        }
    }, true);


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
