(function () {
    'use strict';
    var list = document.getElementById('notify-list');
    if (!list) return;
    var initialId = Number(list.dataset.lastNotificationId) || 0;
    var seen = new Set();
    var pending = new Map();
    var toast, title, message, view, timer;
    var count = 0, remaining = 6000, started = 0;
    var hovered = false, focused = false;

    function safeUrl(value) {
        if (typeof value !== 'string' || !value.trim()) return null;
        try {
            var url = new URL(value, window.location.href);
            return url.origin === window.location.origin && /^https?:$/.test(url.protocol) ? url.href : null;
        } catch (error) { return null; }
    }
    function pause() {
        if (!timer) return;
        window.clearTimeout(timer); timer = null;
        remaining = Math.max(0, remaining - (Date.now() - started));
    }
    function resume() {
        if (!toast || hovered || focused || document.hidden) return;
        pause(); started = Date.now();
        timer = window.setTimeout(dismiss, remaining);
    }
    function dismiss() {
        pause();
        if (toast) toast.remove();
        toast = null; count = 0; hovered = false; focused = false;
    }
    function build() {
        toast = document.createElement('aside');
        toast.className = 'notice-floating';
        toast.setAttribute('aria-label', 'Incoming notification');
        var body = document.createElement('div');
        body.className = 'notice-floating-copy';
        body.setAttribute('role', 'status'); body.setAttribute('aria-live', 'polite');
        body.setAttribute('aria-atomic', 'true');
        title = document.createElement('strong');
        message = document.createElement('p');
        body.appendChild(title); body.appendChild(message);
        var actions = document.createElement('div'); actions.className = 'notice-floating-actions';
        view = document.createElement('a'); view.textContent = 'View';
        var close = document.createElement('button'); close.type = 'button'; close.textContent = 'Dismiss';
        close.setAttribute('aria-label', 'Dismiss incoming notification');
        close.addEventListener('click', dismiss);
        actions.appendChild(view); actions.appendChild(close);
        toast.appendChild(body); toast.appendChild(actions);
        toast.addEventListener('mouseenter', function () { hovered = true; pause(); });
        toast.addEventListener('mouseleave', function () { hovered = false; resume(); });
        toast.addEventListener('focusin', function () { focused = true; pause(); });
        toast.addEventListener('focusout', function (event) {
            if (!toast.contains(event.relatedTarget)) { focused = false; resume(); }
        });
        toast.addEventListener('keydown', function (event) { if (event.key === 'Escape') dismiss(); });
        document.body.appendChild(toast);
    }

    window.dtcIncomingNotifications = function (notifications) {
        notifications.forEach(function (notification) {
            var id = Number(notification.id);
            if (!id || id <= initialId || notification.is_read || seen.has(id)) return;
            pending.set(id, notification);
        });
        if (!pending.size || document.hidden) return;
        var fresh = Array.from(pending.values());
        if (!toast) build();
        count += fresh.length;
        var latest = fresh[fresh.length - 1];
        title.textContent = count > 1 ? count + ' new updates' : String(latest.title || 'New notification');
        message.textContent = count > 1 ? 'Your notification inbox has new activity.' : String(latest.message || '').slice(0, 160);
        // Open is deliberately nonmutating: viewing/dismissing never marks read.
        view.href = safeUrl(count === 1 ? latest.open_url : list.dataset.historyUrl) || safeUrl(list.dataset.historyUrl) || '/notifications';
        fresh.forEach(function (notification) { seen.add(Number(notification.id)); });
        pending.clear();
        pause(); remaining = 6000; resume();
    };
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) pause();
        else { window.dtcIncomingNotifications([]); resume(); }
    });
}());
