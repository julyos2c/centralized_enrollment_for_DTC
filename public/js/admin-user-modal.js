/* Independent password visibility controls, scoped to account administration. */
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editUserModal');
    if (!modal) return;
    const buttons = modal.querySelectorAll('[data-password-toggle]');
    function update(button, visible) {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        input.type = visible ? 'text' : 'password';
        const field = input.id === 'reset-new-password' ? 'new' : 'confirm';
        button.setAttribute('aria-label', (visible ? 'Hide ' : 'Show ') + field + ' password');
        button.setAttribute('aria-pressed', String(visible));
        button.innerHTML = '<i data-lucide="' + (visible ? 'eye-off' : 'eye') + '" aria-hidden="true"></i>';
        if (window.lucide) window.lucide.createIcons({ nodes: [button] });
    }
    buttons.forEach(button => button.addEventListener('click', function () {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (input) update(button, input.type === 'password');
    }));
    function resetVisibility() { buttons.forEach(button => update(button, false)); }
    if (window.jQuery) window.jQuery(modal).on('show.bs.modal hidden.bs.modal', resetVisibility);
    else modal.addEventListener('close', resetVisibility);
});
