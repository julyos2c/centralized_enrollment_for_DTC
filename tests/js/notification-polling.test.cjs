const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(responder, hidden = false, items = [], workBadges = []) {
    const pending = new Map(); let timerId = 0; let requests = 0; let visibility;
    const errors = [];
    const elements = {
        'notify-list': { dataset: { lastNotificationId: '10' },
            querySelector: selector => items.find(item => selector.includes('"' + item.dataset.notificationId + '"')) || {},
            querySelectorAll: () => items.filter(item => !item.removed) },
        'notif-badge': { textContent: '0', hidden: true }, 'notif-subtitle': { textContent: '' },
        'notif-mark-read': { hidden: true }, 'notice-new-updates': { hidden: true }
    };
    const counts = { all: {}, unread: {}, read: {} };
    const document = {
        hidden, currentScript: { dataset: { endpoint: '/notifications/poll', requestTimeout: '10000' } },
        getElementById: id => elements[id], querySelector: selector => counts[selector.match(/"(\w+)"/)[1]],
        querySelectorAll: () => workBadges,
        addEventListener: (event, listener) => visibility = listener,
        createElement: () => ({ setAttribute() {}, appendChild() {} })
    };
    const window = {
        events: {}, addEventListener(event, fn) { this.events[event] = fn; },
        setTimeout: (fn, ms) => { pending.set(++timerId, { fn, ms }); return timerId; },
        clearTimeout: id => pending.delete(id), dtcHttpFailure: status => errors.push(status)
    };
    vm.runInNewContext(fs.readFileSync('public/js/cashier-notification-polling.js', 'utf8'), {
        document, window, AbortController, fetch: async (url, options) => {
            requests++; assert.match(url, /after_id=10/); assert.equal(options.cache, 'no-store');
            assert.ok(options.signal); return typeof responder === 'function' ? responder(requests, options) : responder;
        }
    });
    const fire = delay => {
        const found = Array.from(pending).find(([, timer]) => timer.ms === delay);
        assert.ok(found, 'Expected scheduled delay ' + delay);
        pending.delete(found[0]); return found[1].fn();
    };
    return { elements, counts, document, window, pending, errors, fire, get requests() { return requests; }, visibility: () => visibility() };
}
function ok(notifications = [], extra = {}) {
    return { ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => ({ notifications, unread_count: 2, total_count: 5, read_count: 3, has_more: false, ...extra }) };
}
const flush = () => new Promise(resolve => setImmediate(resolve));

test('incoming notifications update badges and offer list updates without marking read', async () => {
    const app = setup(ok([{ id: 11 }])); await app.fire(0);
    assert.equal(app.requests, 1); assert.equal(app.elements['notif-badge'].textContent, '2');
    assert.equal(app.elements['notice-new-updates'].hidden, false);
    assert.equal(app.elements['notify-list'].dataset.lastNotificationId, '11');
    assert.equal(app.counts.all.textContent, '5'); assert.equal(app.counts.unread.textContent, '2');
    assert.equal(app.pending.size, 1);
});

test('hidden tabs pause polling; 401, 403 and 419 stop all retries', async () => {
    const app = setup(ok(), true); await app.fire(0); assert.equal(app.requests, 0);
    app.document.hidden = false; app.visibility(); await app.fire(0); assert.equal(app.requests, 1);
    for (const status of [401, 403, 419]) {
        const expired = setup({ status }); await expired.fire(0);
        assert.deepEqual(expired.errors, [status]); expired.visibility();
        assert.equal(expired.pending.size, 0); assert.equal(expired.elements['notif-badge'].hidden, true);
    }
});

test('overlapping read-state writes prevent stale polling responses from restoring counts', async () => {
    const app = setup(ok([{ id: 11 }])); app.window.dtcNotificationWrites = 1; await app.fire(0);
    assert.equal(app.elements['notif-badge'].hidden, true);
    assert.equal(app.elements['notify-list'].dataset.lastNotificationId, '10');
});

test('a stalled fetch times out, releases busy state and recovers without overlap', async () => {
    const app = setup(attempt => attempt === 1 ? new Promise(() => {}) : ok());
    const first = app.fire(0); await flush();
    app.visibility(); app.window.events.pageshow(); assert.equal(app.requests, 1);
    app.fire(10000); await first;
    assert.equal(app.elements['notify-list'].dataset.pollStatus, 'timeout');
    await app.fire(5000); assert.equal(app.requests, 2);
    assert.equal(app.elements['notify-list'].dataset.pollStatus, 'connected');
});

test('a stalled JSON body also times out; temporary network and server failures back off', async () => {
    const body = setup({ ...ok(), json: () => new Promise(() => {}) });
    const waiting = body.fire(0); await flush(); body.fire(10000); await waiting;
    assert.equal(body.elements['notify-list'].dataset.pollStatus, 'timeout');
    const app = setup(attempt => { if (attempt === 1) throw new Error('Offline'); if (attempt === 2) return { ok: false, status: 500 }; return ok(); });
    await app.fire(0); await app.fire(5000); await app.fire(10000);
    assert.equal(app.requests, 3); assert.equal(app.elements['notify-list'].dataset.pollStatus, 'connected');
    assert.deepEqual(app.errors, []);
});

test('navigation aborts in-flight work and clears timers without reporting an error', async () => {
    const app = setup(() => new Promise(() => {})); const waiting = app.fire(0); await flush();
    app.window.events.pagehide(); await waiting;
    assert.equal(app.pending.size, 0); assert.deepEqual(app.errors, []);
    assert.notEqual(app.elements['notify-list'].dataset.pollStatus, 'retrying');
});

function item(id) {
    const classes = new Set(['is-unread']);
    const titleClasses = new Set(['is-unread']);
    const toggle = set => ({ toggle: (name, value) => value ? set.add(name) : set.delete(name) });
    const state = { dataset: { read: '0' }, setAttribute() {}, replaceChildren() {}, appendChild() {} };
    const row = { appendChild(dot) { record.dot = dot; dot.remove = () => record.dot = null; } };
    const record = { dataset: { notificationId: String(id), read: '0' }, classList: toggle(classes), classes, state,
        dot: { remove: () => record.dot = null }, remove() { this.removed = true; },
        querySelector(selector) { return selector === '.notif-title' ? { classList: toggle(titleClasses) } : selector === '.notif-dot' ? record.dot : selector === '.notif-row' ? row : state; } };
    return record;
}

test('authoritative snapshots synchronize Tab B read styling/counts and mark-all without duplicates', async () => {
    const record = item(10); let read = true;
    const app = setup(() => ok([], { unread_count: read ? 0 : 1, recent_notifications: [{ id: 10, is_read: read }] }), false, [record]);
    await app.fire(0);
    assert.equal(app.elements['notif-badge'].hidden, true); assert.equal(record.dataset.read, '1');
    assert.equal(record.classes.has('is-read'), true); assert.equal(record.classes.has('is-unread'), false);
    assert.equal(record.dot, null); assert.equal(record.state.dataset.read, '1');
    read = false; await app.fire(5000);
    assert.equal(app.elements['notif-badge'].textContent, '1'); assert.equal(record.dataset.read, '0');
    assert.ok(record.dot); assert.equal(record.state.dataset.read, '0');
    read = true; await app.fire(5000); assert.equal(record.dot, null);
    assert.equal(record.removed, undefined); assert.equal(app.elements['notify-list'].dataset.lastNotificationId, '10');
});

test('work badges update independently, hide zeros and cap displayed counts at 99+', async () => {
    const badge = { dataset: { workCount: 'cashier.payments.index', workDescription: 'enrollments awaiting payment' }, hidden: true,
        setAttribute(key, value) { this[key] = value; } };
    let count = 125;
    const app = setup(() => ok([], { unread_count: 0, work_counts: { 'cashier.payments.index': count } }), false, [], [badge]);
    await app.fire(0);
    assert.equal(badge.textContent, '99+'); assert.equal(badge.hidden, false);
    assert.equal(badge['aria-label'], '125 enrollments awaiting payment');
    assert.equal(app.elements['notif-badge'].hidden, true);
    count = 0; await app.fire(5000);
    assert.equal(badge.hidden, true); assert.equal(badge.textContent, '0');
});
