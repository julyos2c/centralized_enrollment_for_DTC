const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    class Element {
        constructor() { this.children = []; this.events = {}; }
        appendChild(child) { this.children.push(child); child.parent = this; }
        setAttribute(key, value) { this[key] = value; }
        addEventListener(event, listener) { this.events[event] = listener; }
        remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
        contains(node) { return this.children.includes(node); }
    }
    const timers = new Map(); let id = 0;
    const document = { hidden: false, body: new Element(), events: {},
        getElementById: () => ({ dataset: { lastNotificationId: '10', historyUrl: '/notifications' } }),
        createElement: () => new Element(), addEventListener(event, fn) { this.events[event] = fn; } };
    const window = { location: { href: 'https://dtc.test/portal/dashboard', origin: 'https://dtc.test' },
        setTimeout: (fn, ms) => { timers.set(++id, { fn, ms }); return id; }, clearTimeout: key => timers.delete(key) };
    vm.runInNewContext(fs.readFileSync('public/js/notification-toasts.js', 'utf8'), { document, window, URL, Set, Date });
    return { document, window, timers, show: window.dtcIncomingNotifications, toast: () => document.body.children[0] };
}
const notice = id => ({ id, title: 'Document ready', message: 'Collect your document.', open_url: '/notifications/' + id + '/open', is_read: false });

test('historical, read and replayed notifications never create floating duplicates', () => {
    const app = setup();
    app.show([notice(9), notice(10), { ...notice(11), is_read: true }]);
    assert.equal(app.toast(), undefined);
    app.show([notice(12)]);
    const toast = app.toast();
    assert.equal(toast.children[0].children[0].textContent, 'Document ready');
    assert.equal(toast.children[1].children[0].href, 'https://dtc.test/notifications/12/open');
    app.show([notice(12)]);
    assert.equal(app.document.body.children.length, 1);
    toast.children[1].children[1].events.click();
    app.show([notice(12)]);
    assert.equal(app.toast(), undefined);
});

test('bursts group into one alert and untrusted destinations fall back to the inbox', () => {
    const app = setup();
    app.show([{ ...notice(11), title: '<script>alert(1)</script>', open_url: 'https://evil.test/' }]);
    assert.equal(app.toast().children[0].children[0].textContent, '<script>alert(1)</script>');
    assert.equal(app.toast().children[1].children[0].href, 'https://dtc.test/notifications');
    app.show([notice(12), notice(13)]);
    assert.equal(app.document.body.children.length, 1);
    assert.equal(app.toast().children[0].children[0].textContent, '3 new updates');
    assert.equal(app.toast().children[1].children[0].href, 'https://dtc.test/notifications');
});

test('dismissal pauses for hover, keyboard focus and hidden tabs, then resumes', () => {
    const app = setup(); app.show([notice(11)]);
    const toast = app.toast();
    assert.equal([...app.timers.values()][0].ms, 6000);
    toast.events.mouseenter(); assert.equal(app.timers.size, 0);
    toast.events.mouseleave(); assert.equal(app.timers.size, 1);
    toast.events.focusin(); assert.equal(app.timers.size, 0);
    toast.events.focusout({ relatedTarget: null }); assert.equal(app.timers.size, 1);
    app.document.hidden = true; app.document.events.visibilitychange(); assert.equal(app.timers.size, 0);
    app.document.hidden = false; app.document.events.visibilitychange(); assert.equal(app.timers.size, 1);
    [...app.timers.values()][0].fn(); assert.equal(app.toast(), undefined);
});

test('a response received while hidden stays pending until actually displayed', () => {
    const app = setup();
    app.document.hidden = true;
    app.show([notice(11)]);
    app.show([notice(11)]);
    assert.equal(app.toast(), undefined);
    app.document.hidden = false;
    app.document.events.visibilitychange();
    assert.equal(app.toast().children[0].children[0].textContent, 'Document ready');
    app.toast().children[1].children[1].events.click();
    app.show([notice(11)]);
    assert.equal(app.toast(), undefined);
});
