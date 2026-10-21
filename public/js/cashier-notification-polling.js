(function () {
    'use strict';

    var script = document.currentScript;
    var list = document.getElementById('notify-list');
    if (!script || !list || !script.dataset.endpoint) return;

    var endpoint = script.dataset.endpoint;
    var lastId = parseInt(list.dataset.lastNotificationId || '0', 10) || 0;
    var timer = null;
    var busy = false;
    var stopped = false;
    var allowedTypes = ['info', 'success', 'warning', 'danger'];
    var icons = { info: 'info', success: 'check-circle', warning: 'alert-circle', danger: 'x-circle' };

    function updateUnread(count) {
        var badge = document.getElementById('notif-badge');
        var bell = document.getElementById('notifBell');
        var subtitle = document.getElementById('notif-subtitle');
        var markRead = document.getElementById('notif-mark-read');

        count = Math.max(0, parseInt(count, 10) || 0);
        if (count && !badge && bell) {
            badge = document.createElement('span');
            badge.id = 'notif-badge';
            badge.className = 'navbar-notification-badge';
            bell.appendChild(badge);
        }
        if (badge) {
            badge.textContent = count ? String(count) : '';
            badge.hidden = !count;
        }
        if (subtitle) {
            subtitle.textContent = count ? count + ' unread notification' + (count === 1 ? '' : 's') : 'All caught up!';
        }
        if (markRead) markRead.hidden = !count;
    }

    function appendNotification(notification) {
        var id = parseInt(notification.id, 10);
        if (!id || list.querySelector('[data-notification-id="' + id + '"]')) return;

        var empty = list.querySelector('.notify-empty');
        if (empty) empty.remove();

        var type = allowedTypes.indexOf(notification.type) !== -1 ? notification.type : 'info';
        var item = document.createElement('a');
        item.className = 'notif-item ' + (notification.is_read ? 'is-read' : 'is-unread');
        item.href = notification.read_url;
        item.dataset.notificationId = String(id);

        var icon = document.createElement('div');
        icon.className = 'notif-icon notif-icon-' + type;
        var iconGlyph = document.createElement('i');
        iconGlyph.setAttribute('data-lucide', icons[type]);
        icon.appendChild(iconGlyph);

        var body = document.createElement('div');
        body.className = 'notif-body';
        var row = document.createElement('div');
        row.className = 'notif-row';
        var title = document.createElement('div');
        title.className = 'notif-title ' + (notification.is_read ? 'is-read' : 'is-unread');
        title.textContent = notification.title || 'Notification';
        row.appendChild(title);
        if (!notification.is_read) {
            var dot = document.createElement('div');
            dot.className = 'notif-dot';
            dot.setAttribute('aria-label', 'Unread notification');
            row.appendChild(dot);
        }

        var message = document.createElement('div');
        message.className = 'notif-message';
        message.textContent = notification.message || '';
        var time = document.createElement('div');
        time.className = 'notif-time';
        var clock = document.createElement('i');
        clock.setAttribute('data-lucide', 'clock');
        clock.setAttribute('aria-hidden', 'true');
        time.appendChild(clock);
        time.appendChild(document.createTextNode(notification.relative_time || 'Just now'));

        body.appendChild(row);
        body.appendChild(message);
        body.appendChild(time);
        item.appendChild(icon);
        item.appendChild(body);
        list.insertBefore(item, list.firstChild);

        var items = list.querySelectorAll('[data-notification-id]');
        while (items.length > 5) {
            items[items.length - 1].remove();
            items = list.querySelectorAll('[data-notification-id]');
        }
        var footer = document.getElementById('notify-footer');
        if (footer) footer.hidden = false;
        if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
    }

    function schedule(delay) {
        if (stopped) return;
        window.clearTimeout(timer);
        timer = window.setTimeout(poll, delay);
    }

    async function poll() {
        if (stopped || busy) return schedule(1000);
        busy = true;
        try {
            var response = await fetch(endpoint + '?after_id=' + encodeURIComponent(lastId), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (response.status === 401 || response.status === 403) {
                stopped = true;
                return;
            }
            if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('Notification polling unavailable');
            }

            var result = await response.json();
            (result.notifications || []).forEach(function (notification) {
                appendNotification(notification);
                lastId = Math.max(lastId, parseInt(notification.id, 10) || 0);
            });
            list.dataset.lastNotificationId = String(lastId);
            updateUnread(result.unread_count);
            schedule(result.has_more ? 100 : 5000);
        } catch (error) {
            // The database-backed notification remains available on page reload.
            // Keep polling so a temporary network outage recovers automatically.
            schedule(5000);
        } finally {
            busy = false;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) schedule(0);
    });
    schedule(0);
}());
