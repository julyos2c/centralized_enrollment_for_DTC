(function () {
    'use strict';

    function updateCount(count) {
        count = Math.max(0, Number(count) || 0);
        var badge = document.getElementById('notif-badge');
        var bell = document.getElementById('notifBell');
        if (!badge && bell) {
            badge = document.createElement('span');
            badge.id = 'notif-badge';
            badge.className = 'navbar-notification-badge';
            bell.appendChild(badge);
        }
        if (badge) { badge.textContent = count > 99 ? '99+' : String(count); badge.hidden = !count; }
        var subtitle = document.getElementById('notif-subtitle');
        if (subtitle) subtitle.textContent = count ? count + ' unread' : 'All caught up';
        var markAll = document.getElementById('notif-mark-read');
        if (markAll) markAll.hidden = !count;
    }

    function announce(message) {
        var region = document.getElementById('notice-live');
        if (!region) {
            region = document.createElement('div');
            region.id = 'notice-live'; region.className = 'sr-only';
            region.setAttribute('role', 'status');
            region.setAttribute('aria-live', 'polite');
            document.body.appendChild(region);
        }
        region.textContent = message;
    }

    async function mutate(button, method, data, mode) {
        if (button.disabled) return;
        window.dtcNotificationWrites = (window.dtcNotificationWrites || 0) + 1;
        window.dtcNotificationRevision = (window.dtcNotificationRevision || 0) + 1;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            var response = await window.dtcFetch(button.dataset.url, {
                method: method,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json', 'Content-Type': 'application/json'
                },
                body: data ? JSON.stringify(data) : undefined
            });
            var result = await response.json();
            if (!result.success) throw new Error('Notification change was not saved.');
            updateCount(result.unread_count);
            if (mode === 'all') {
                document.querySelectorAll('#notify-list .notif-item').forEach(function (item) {
                    item.classList.remove('is-unread'); item.classList.add('is-read');
                    item.dataset.read = '1';
                    var title = item.querySelector('.notif-title');
                    if (title) { title.classList.remove('is-unread'); title.classList.add('is-read'); }
                    var dot = item.querySelector('.notif-dot');
                    if (dot) dot.remove();
                    var control = item.querySelector('[data-notice-state]');
                    if (control) setState(control, true);
                });
                announce('All notifications marked as read.');
            } else if (mode === 'state') {
                setState(button, result.read);
                var item = button.closest('.notif-item');
                if (item) {
                    item.classList.toggle('is-unread', !result.read);
                    item.classList.toggle('is-read', result.read);
                    item.dataset.read = result.read ? '1' : '0';
                    var dot = item.querySelector('.notif-dot');
                    if (dot) dot.remove();
                    if (!result.read) {
                        dot = document.createElement('span'); dot.className = 'notif-dot';
                        dot.setAttribute('aria-label', 'Unread notification');
                        item.querySelector('.notif-row').appendChild(dot);
                    }
                }
                announce(result.read ? 'Notification marked as read.' : 'Notification marked unread.');
            }
            // Server pagination and counts are authoritative. Keep the current URL/filters.
            if (document.querySelector('.notice-page')) window.location.reload();
        } catch (error) {
            if (window.dtcRequestFailure) window.dtcRequestFailure(error);
        } finally {
            window.dtcNotificationWrites--;
            window.dtcNotificationRevision++;
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    }

    function setState(button, read) {
        button.dataset.read = read ? '1' : '0';
        var label = read ? 'Mark unread' : 'Mark as read';
        button.title = label;
        if (button.classList.contains('notice-dropdown-state')) {
            button.setAttribute('aria-label', label);
            button.replaceChildren();
            var glyph = document.createElement('i');
            glyph.setAttribute('data-lucide', read ? 'mail' : 'check');
            glyph.setAttribute('aria-hidden', 'true');
            button.appendChild(glyph);
            if (window.lucide) window.lucide.createIcons();
        } else button.textContent = label;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-notice-state], [data-notice-all], [data-notice-delete], [data-notice-delete-read]');
        if (!button) return;
        event.preventDefault(); event.stopPropagation();
        if (button.hasAttribute('data-notice-state')) {
            mutate(button, 'POST', { read: button.dataset.read !== '1' }, 'state');
        } else if (button.hasAttribute('data-notice-all')) {
            mutate(button, 'POST', null, 'all');
        } else {
            if (!window.dtcConfirm) return;
            window.dtcConfirm.open({
                trigger: button, tone: 'danger', title: 'Delete notification' + (button.hasAttribute('data-notice-delete-read') ? 's?' : '?'),
                message: button.hasAttribute('data-notice-delete-read') ? 'All read notifications will be permanently removed.' : 'This notification will be permanently removed.',
                okLabel: 'Delete', onConfirm: function () { return mutate(button, 'DELETE', null, 'delete'); }
            });
        }
    }, true);
}());
