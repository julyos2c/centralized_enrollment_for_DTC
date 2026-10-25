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
    var suspended = false;
    var intentionalAbort = false;
    var controller = null;
    var failures = 0;
    var requestTimeout = Math.max(1000, Math.min(30000, Number(script.dataset.requestTimeout) || 10000));
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
            badge.textContent = count ? (count > 99 ? '99+' : String(count)) : '';
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
        var item = document.createElement('div');
        item.className = 'notif-item ' + (notification.is_read ? 'is-read' : 'is-unread');
        item.dataset.read = notification.is_read ? '1' : '0';
        item.dataset.notificationId = String(id);

        var icon = document.createElement('div');
        icon.className = 'notif-icon notif-icon-' + type;
        var iconGlyph = document.createElement('i');
        iconGlyph.setAttribute('data-lucide', notification.category_icon || icons[type]);
        icon.appendChild(iconGlyph);

        var body = document.createElement('a');
        body.className = 'notif-body';
        body.href = notification.open_url || notification.read_url;
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
        if (notification.state_url) {
            var state = document.createElement('button');
            state.type = 'button'; state.className = 'notice-dropdown-state';
            state.setAttribute('data-notice-state', '');
            state.dataset.url = notification.state_url;
            state.dataset.read = notification.is_read ? '1' : '0';
            var stateLabel = notification.is_read ? 'Mark unread' : 'Mark as read';
            state.title = stateLabel; state.setAttribute('aria-label', stateLabel);
            var stateIcon = document.createElement('i');
            stateIcon.setAttribute('data-lucide', notification.is_read ? 'mail' : 'check');
            stateIcon.setAttribute('aria-hidden', 'true');
            state.appendChild(stateIcon); item.appendChild(state);
        }
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

    function synchronizeState(notification) {
        var item = list.querySelector('[data-notification-id="' + Number(notification.id) + '"]');
        if (!item) return;
        var read = !!notification.is_read;
        item.dataset.read = read ? '1' : '0';
        item.classList.toggle('is-read', read);
        item.classList.toggle('is-unread', !read);
        var title = item.querySelector('.notif-title');
        if (title) { title.classList.toggle('is-read', read); title.classList.toggle('is-unread', !read); }
        var dot = item.querySelector('.notif-dot');
        if (read && dot) dot.remove();
        if (!read && !dot) {
            dot = document.createElement('span'); dot.className = 'notif-dot';
            dot.setAttribute('aria-label', 'Unread notification');
            item.querySelector('.notif-row').appendChild(dot);
        }
        var state = item.querySelector('[data-notice-state]');
        if (state && state.dataset.read !== item.dataset.read) {
            state.dataset.read = item.dataset.read;
            var label = read ? 'Mark unread' : 'Mark as read';
            state.title = label; state.setAttribute('aria-label', label);
            state.replaceChildren();
            var glyph = document.createElement('i');
            glyph.setAttribute('data-lucide', read ? 'mail' : 'check');
            glyph.setAttribute('aria-hidden', 'true'); state.appendChild(glyph);
        }
    }

    function schedule(delay) {
        if (stopped || suspended) return;
        window.clearTimeout(timer);
        timer = window.setTimeout(poll, delay);
    }

    async function poll() {
        if (stopped || suspended) return;
        if (busy) return;
        if (document.hidden) return schedule(5000);
        busy = true;
        var revision = window.dtcNotificationRevision || 0;
        intentionalAbort = false;
        controller = new AbortController();
        var timedOut = false;
        var aborted = new Promise(function (resolve, reject) {
            controller.signal.addEventListener('abort', function () { reject(new Error('Polling aborted')); }, { once: true });
        });
        var timeoutTimer = window.setTimeout(function () { timedOut = true; controller.abort(); }, requestTimeout);
        try {
            var welcomeQuery = window.dtcStudentWelcome ? window.dtcStudentWelcome.pollQuery() : '';
            var response = await Promise.race([fetch(endpoint + '?after_id=' + encodeURIComponent(lastId) + welcomeQuery, {
                signal: controller.signal,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }), aborted]);
            if (response.status === 401 || response.status === 403 || response.status === 419) {
                stopped = true;
                window.clearTimeout(timer);
                list.dataset.pollStatus = 'expired';
                if (window.dtcHttpFailure) window.dtcHttpFailure(response.status);
                return;
            }
            if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('Notification polling unavailable');
            }

            var result = await Promise.race([response.json(), aborted]);
            if (window.dtcStudentWelcome) window.dtcStudentWelcome.receive(result.student_welcome);
            failures = 0;
            list.dataset.pollStatus = 'connected';
            // A response started before a read-state write must not restore an old badge.
            if (window.dtcNotificationWrites || revision !== (window.dtcNotificationRevision || 0)) {
                schedule(1000);
                return;
            }
            var newUpdates = document.getElementById('notice-new-updates');
            if (result.work_counts) {
                document.querySelectorAll('[data-work-count]').forEach(function (badge) {
                    var value = result.work_counts[badge.dataset.workCount];
                    if (!Number.isInteger(value) || value < 0) return;
                    badge.textContent = value > 99 ? '99+' : String(value);
                    badge.hidden = value === 0;
                    badge.setAttribute('aria-label', value + ' ' + badge.dataset.workDescription);
                });
            }
            if (newUpdates && (result.notifications || []).some(function (notice) { return Number(notice.id) > lastId; })) {
                newUpdates.hidden = false;
            }
            ['all', 'unread', 'read'].forEach(function (key) {
                var counter = document.querySelector('[data-notice-count="' + key + '"]');
                var count = result[key === 'all' ? 'total_count' : key + '_count'];
                if (counter && typeof count === 'number') counter.textContent = String(count);
            });
            if (window.dtcIncomingNotifications) {
                window.dtcIncomingNotifications((result.notifications || []).filter(function (notification) {
                    return Number(notification.id) > lastId && !notification.is_read;
                }));
            }
            (result.notifications || []).forEach(function (notification) {
                appendNotification(notification);
                lastId = Math.max(lastId, parseInt(notification.id, 10) || 0);
            });
            if (!result.has_more && Array.isArray(result.recent_notifications)) {
                var recent = result.recent_notifications;
                var ids = recent.map(function (notification) { return String(notification.id); });
                list.querySelectorAll('[data-notification-id]').forEach(function (item) {
                    if (ids.indexOf(item.dataset.notificationId) === -1) item.remove();
                });
                recent.slice().reverse().forEach(function (notification) {
                    appendNotification(notification);
                    synchronizeState(notification);
                });
                if (window.lucide) window.lucide.createIcons();
            }
            list.dataset.lastNotificationId = String(lastId);
            updateUnread(result.unread_count);
            schedule(result.has_more ? 100 : 5000);
        } catch (error) {
            if (intentionalAbort) {
                if (!suspended) schedule(0);
            } else if (!stopped && !suspended) {
                list.dataset.pollStatus = timedOut ? 'timeout' : 'retrying';
                failures++;
                schedule(Math.min(60000, 5000 * Math.pow(2, Math.min(failures - 1, 4))));
            }
        } finally {
            window.clearTimeout(timeoutTimer);
            controller = null;
            busy = false;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && !busy) schedule(0);
    });
    window.addEventListener('pagehide', function () {
        suspended = true;
        intentionalAbort = true;
        window.clearTimeout(timer);
        if (controller) controller.abort();
    });
    window.addEventListener('pageshow', function () {
        suspended = false;
        if (!stopped && !busy) schedule(0);
    });
    schedule(0);
}());
