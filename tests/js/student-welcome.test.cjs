const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup({ mobile = false, reduced = false, channelAvailable = true, applicantPage = false } = {}) {
    class Element {
        constructor() { this.events = {}; this.children = []; this.style = {}; this.isConnected = true; }
        addEventListener(name, fn) { this.events[name] = fn; }
        setAttribute(name, value) { this[name] = value; }
        removeAttribute(name) { delete this[name]; }
        appendChild(node) { this.children.push(node); node.parent = this; }
        remove() { this.parent.children = this.parent.children.filter(node => node !== this); }
        focus() { document.activeElement = this; }
        contains(node) { return this === node || this.children.some(child => child.contains(node)); }
        querySelector(selector) { return selector === '[role="dialog"]' ? dialog : this.children.find(child => child.role === 'alert'); }
    }
    const root = new Element(), dialog = new Element(), open = new Element(), previous = new Element();
    root.dataset = { endpoint: '/notifications/student-welcome', user: '7', dashboardUrl: '/portal/dashboard', redirectAfterAck: applicantPage ? '1' : '0' }; root.hidden = true;
    root.appendChild(dialog); dialog.appendChild(open);
    const document = { hidden: false, activeElement: previous, body: new Element(), events: {}, modal: false,
        getElementById: id => ({ studentWelcome: root, studentWelcomeOpen: open })[id],
        querySelector: selector => selector.startsWith('meta') ? { content: 'csrf' } : document.modal,
        createElement: () => new Element(), addEventListener(name, fn) { this.events[name] = fn; } };
    const timers = new Map(), requests = [], messages = [], storage = [], channel = {};
    let timerId = 0, fail = false, accepted = true, destination;
    const window = { events: {}, crypto: { randomUUID: () => '11111111-1111-4111-8111-111111111111' },
        setTimeout(fn, ms) { timers.set(++timerId, { fn, ms }); return timerId; }, clearTimeout(id) { timers.delete(id); },
        addEventListener(name, fn) { this.events[name] = fn; },
        matchMedia: query => ({ matches: query.includes('reduced-motion') ? reduced : mobile }),
        location: { assign(url) { destination = url; } } };
    function BroadcastChannel() { if (!channelAvailable) throw new Error(); channel.postMessage = msg => messages.push(msg); channel.close = () => {}; return channel; }
    vm.runInNewContext(fs.readFileSync('public/js/student-welcome.js', 'utf8'), {
        document, window, BroadcastChannel, AbortController, Set, Uint8Array,
        localStorage: { setItem: (key, value) => storage.push({ key, value }) },
        fetch: async (url, options) => {
            requests.push({ url, options, data: JSON.parse(options.body) });
            if (fail) throw new Error('offline');
            return { ok: true, headers: { get: () => 'application/json' }, json: async () => ({ accepted }) };
        }
    });
    return { root, open, previous, dialog, document, window, timers, requests, channel, messages, storage,
        receive: window.dtcStudentWelcome.receive, destination: () => destination,
        fail: () => { fail = true; }, refuse: () => { accepted = false; } };
}
const flush = () => new Promise(resolve => setImmediate(resolve));

test('welcome claims once, traps focus on its single button, and Continue acknowledges without leaving a shared page', async () => {
    const app = setup();
    await app.receive({ id: 4 });
    assert.equal(app.root.hidden, false);
    assert.equal(app.document.activeElement, app.open);
    await app.receive({ id: 4, lease_owned: true });
    assert.equal(app.requests.length, 1);
    assert.match(app.window.dtcStudentWelcome.pollQuery(), /welcome_token=/);
    assert.equal(app.requests[0].options.headers['X-CSRF-TOKEN'], 'csrf');
    app.document.events.keydown({ key: 'Tab', preventDefault() {} });
    assert.equal(app.document.activeElement, app.open);
    app.document.events.keydown({ key: 'Tab', shiftKey: true, preventDefault() {} });
    assert.equal(app.document.activeElement, app.open);
    app.open.events.click(); await flush();
    assert.equal(app.requests[1].data.action, 'acknowledge');
    assert.equal(app.root.hidden, true);
    assert.equal(app.document.activeElement, app.previous);
    assert.equal(app.destination(), undefined);
    await app.receive({ id: 4 });
    assert.equal(app.requests.length, 2);
});

test('applicant-only navigation waits for acknowledgement; failures keep the dialog and work intact', async () => {
    const app = setup({ applicantPage: true }); await app.receive({ id: 4 });
    app.open.events.click({ preventDefault() {} }); await flush();
    assert.equal(app.destination(), '/portal/dashboard');
    const failed = setup({ applicantPage: true }); await failed.receive({ id: 5 }); failed.fail();
    failed.open.events.click({ preventDefault() {} }); await flush();
    assert.equal(failed.root.hidden, false);
    assert.equal(failed.destination(), undefined);
    assert.equal(failed.dialog.children.at(-1).role, 'alert');
    assert.equal(failed.open.disabled, false);
});

test('backdrop and Escape count as acknowledgement and only redirect applicant-only pages', async () => {
    for (const applicantPage of [true, false]) {
        for (const dismissal of ['backdrop', 'escape']) {
            const app = setup({ applicantPage }); await app.receive({ id: 7 });
            if (dismissal === 'backdrop') {
                app.root.events.click({ target: app.dialog });
                assert.equal(app.requests.length, 1);
                app.root.events.click({ target: app.root });
            } else {
                app.document.events.keydown({ key: 'Escape', preventDefault() {}, stopImmediatePropagation() {} });
            }
            await flush();
            assert.equal(app.requests.at(-1).data.action, 'acknowledge');
            assert.equal(app.root.hidden, true);
            assert.equal(app.destination(), applicantPage ? '/portal/dashboard' : undefined);
        }
    }
});

test('confetti respects piece counts, cleanup, and reduced motion', async () => {
    for (const [mobile, count] of [[false, 28], [true, 16]]) {
        const app = setup({ mobile }); await app.receive({ id: 4 });
        assert.equal(app.document.body.children.find(node => node.className === 'student-welcome-confetti').children.length, count);
        [...app.timers.values()].find(timer => timer.ms === 6000).fn();
        assert.equal(app.document.body.children.filter(node => node.className === 'student-welcome-confetti').length, 0);
    }
    const app = setup({ reduced: true }); await app.receive({ id: 4 });
    assert.equal(app.document.body.children.filter(node => node.className === 'student-welcome-confetti').length, 0);
});

test('claims and acknowledgements synchronize through BroadcastChannel and storage fallback', async () => {
    for (const channelAvailable of [true, false]) {
        const app = setup({ channelAvailable }); await app.receive({ id: 4 });
        const message = { type: 'acknowledged', id: 4 };
        if (channelAvailable) app.channel.onmessage({ data: message });
        else app.window.events.storage({ key: 'dtc-student-welcome:7', newValue: JSON.stringify(message) });
        assert.equal(app.root.hidden, true);
        await app.receive({ id: 4 });
        assert.equal(app.requests.length, 1);
    }
    const refused = setup(); refused.refuse(); await refused.receive({ id: 4 });
    assert.equal(refused.root.hidden, true);
    const occupied = setup(); await occupied.receive({ id: 4, available: false });
    assert.equal(occupied.requests.length, 0);
});

test('welcome defers behind payment modals, releases hidden tabs, and Escape acknowledges', async () => {
    const app = setup(); app.document.modal = true;
    await app.receive({ id: 4 }); assert.equal(app.requests.length, 0);
    app.document.modal = false; await app.receive({ id: 4 });
    app.document.hidden = true; app.document.events.visibilitychange(); await flush();
    assert.equal(app.root.hidden, true);
    assert.equal(app.requests.at(-1).data.action, 'release');
    app.document.hidden = false; await app.receive({ id: 4 });
    app.document.events.keydown({ key: 'Escape', preventDefault() {}, stopImmediatePropagation() {} }); await flush();
    assert.equal(app.requests.at(-1).data.action, 'acknowledge');
    assert.equal(app.root.hidden, true);
});
