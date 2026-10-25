(function () {
    'use strict';
    if (window.dtcPasswordGuidance) return;
    window.dtcPasswordGuidance = true;
    function node(tag, className, text) {
        const element = document.createElement(tag);
        element.className = className;
        if (text) element.textContent = text;
        return element;
    }
    document.querySelectorAll('form').forEach(function (form, index) {
        const password = form.querySelector('input[name="password"]');
        const confirmation = form.querySelector('input[name="password_confirmation"]');
        if (!password || (!confirmation && !form.hasAttribute('data-password-admin'))) return;
        const policy = document.getElementById('dtc-password-policy');
        const minimum = policy ? Number(policy.dataset.minimum) : (password.minLength > 0 ? password.minLength : 8);
        const requiresSymbols = policy && policy.dataset.symbols === '1';
        const admin = form.hasAttribute('data-password-admin');
        const current = form.querySelector('input[name="current_password"]');
        password.minLength = minimum;
        password.autocomplete = 'new-password';
        password.required = true;
        if (confirmation) { confirmation.required = true; confirmation.autocomplete = 'new-password'; }
        const helper = node('div', 'pw-guidance');
        helper.id = 'password-guidance-' + index;
        const requirement = node('p', 'pw-requirement', 'At least ' + minimum + ' characters');
        const advice = node('p', 'pw-advice', (requiresSymbols ? 'Include at least one symbol. ' : '') + 'Use a long, unique password. Spaces and pasting are welcome.');
        const match = node('p', 'pw-match');
        match.setAttribute('aria-live', 'polite');
        helper.append(requirement, advice, match);
        const anchor = confirmation || password;
        const field = anchor.closest('.auth-field, .form-group, .ps-field') || anchor.parentElement;
        field.insertAdjacentElement('afterend', helper);
        [password, confirmation].filter(Boolean).forEach(function (input) {
            const ids = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            ids.push(helper.id);
            input.setAttribute('aria-describedby', ids.join(' '));
        });
        let touched = false;
        let busy = false;
        let confirmed = false;
        let toolStatus = null;
        function update() {
            const length = Array.from(password.value).length;
            requirement.dataset.valid = String(length >= minimum);
            requirement.textContent = (length >= minimum ? '✓ ' : '') + 'At least ' + minimum + ' characters';
            password.setCustomValidity(current && form.hasAttribute('data-password-distinct') && password.value && password.value === current.value
                ? 'Choose a password different from your temporary password.' : '');
            if (!confirmation) return;
            const matches = !!password.value && password.value === confirmation.value;
            confirmation.setCustomValidity(confirmation.value && !matches ? 'Passwords do not match.' : '');
            match.textContent = matches ? 'Passwords match' : touched && confirmation.value ? 'Passwords do not match' : '';
            match.dataset.valid = String(matches);
        }
        password.addEventListener('input', update);
        if (current) current.addEventListener('input', update);
        if (confirmation) {
            confirmation.addEventListener('input', update);
            confirmation.addEventListener('blur', function () { touched = true; update(); });
        }
        [current, password, confirmation].filter(Boolean).forEach(function (input) {
            const wrap = input.parentElement;
            if (wrap.querySelector('.auth-eye, .ps-pw-toggle, [data-password-toggle]')) return;
            const button = node('button', 'pw-visibility');
            button.type = 'button';
            function renderVisibility(visible) {
                button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                button.setAttribute('aria-pressed', String(visible));
                if (form.hasAttribute('data-password-icon-toggles')) {
                    button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>' + (visible ? '<path d="m3 3 18 18"/>' : '') + '</svg>';
                } else button.textContent = visible ? 'Hide' : 'Show';
            }
            renderVisibility(false);
            input.insertAdjacentElement('afterend', button);
            button.addEventListener('click', function () {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                renderVisibility(show);
            });
        });
        if (admin) {
            const tools = node('div', 'pw-tools');
            const generate = node('button', 'pw-tool', 'Generate password');
            const copy = node('button', 'pw-tool', 'Copy password');
            const status = node('p', 'pw-advice');
            toolStatus = status;
            status.setAttribute('aria-live', 'polite');
            generate.type = copy.type = 'button';
            tools.append(generate, copy);
            helper.append(tools, status);
            generate.addEventListener('click', function () {
                const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
                const bytes = window.crypto.getRandomValues(new Uint8Array(Math.max(16, minimum)));
                password.value = Array.from(bytes, value => alphabet[value & 63]).join('');
                if (requiresSymbols) password.value = password.value.slice(0, -1) + '!';
                if (confirmation) confirmation.value = password.value;
                touched = true;
                update();
                status.textContent = 'Generated. Copy it before saving and share it privately with the user.';
            });
            copy.addEventListener('click', async function () {
                if (!password.value) { status.textContent = 'Enter or generate a password first.'; return; }
                try { await navigator.clipboard.writeText(password.value); status.textContent = 'Password copied.'; }
                catch (error) { status.textContent = 'Copy unavailable. Use Show to copy the password manually.'; }
            });
        }
        const submits = Array.from(form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]'));
        const originals = submits.map(button => ({ button, html: button.innerHTML, value: button.value, disabled: button.disabled }));
        function restore() {
            busy = false; confirmed = false; touched = false;
            originals.forEach(function (item) {
                item.button.disabled = item.disabled;
                if (item.button.tagName === 'INPUT') item.button.value = item.value;
                else item.button.innerHTML = item.html;
            });
            form.removeAttribute('aria-busy');
            if (toolStatus) toolStatus.textContent = '';
            update();
        }
        form.addEventListener('submit', function (event) {
            touched = true; update();
            if (busy || !form.checkValidity()) {
                event.preventDefault();
                if (!busy) { form.reportValidity(); const invalid = form.querySelector(':invalid'); if (invalid) invalid.focus(); }
                return;
            }
            if (admin && confirmation && !confirmed && window.dtcConfirm) {
                event.preventDefault();
                const name = form.dataset.passwordAccount || (document.getElementById('edit-modal-name') || {}).textContent || 'this account';
                window.dtcConfirm.open({ trigger: event.submitter || submits[0], title: 'Reset user password?', message: 'Reset the password for ' + name + '? The user must change it at their next login.',
                    okLabel: 'Reset password', tone: 'warning', onConfirm: function () { confirmed = true; form.requestSubmit(); } });
                return;
            }
            busy = true;
            form.setAttribute('aria-busy', 'true');
            submits.forEach(function (button) {
                button.disabled = true;
                if (button.tagName === 'INPUT') button.value = 'Saving…';
                else button.textContent = 'Saving…';
            });
        });
        form.addEventListener('reset', function () { window.setTimeout(restore, 0); });
        window.addEventListener('pageshow', restore);
        const modal = form.closest('.modal');
        if (modal && window.jQuery) window.jQuery(modal).on('show.bs.modal', restore);
        update();
    });
})();
