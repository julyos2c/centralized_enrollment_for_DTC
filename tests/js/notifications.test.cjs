const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function harness(fetcher, mode = 'state') {
    const elements = {
        'notif-badge': { textContent: '3', hidden: false },
        'notif-subtitle': { textContent: '3 unread' },
        'notif-mark-read': { hidden: false },
        'notice-live': { textContent: '' }
    };
    const attributes = {};
    const button = {
        disabled: false, dataset: { url: '/notifications/1/state', read: '0' }, textContent: 'Mark as read',
        classList: { contains: () => false },
        setAttribute: (key, value) => attributes[key] = value,
        removeAttribute: key => delete attributes[key],
        hasAttribute: key => key === 'data-notice-' + mode,
        closest: selector => selector.includes('[data-notice-') ? button : null
    };
    let listener; let capture; const errors = []; let confirmation;
    const document = {
        addEventListener: (event, handler, flag) => { listener = handler; capture = flag; },
        getElementById: id => elements[id] || null,
        querySelector: selector => selector.includes('csrf-token') ? { content: 'test-csrf' } : null,
        querySelectorAll: () => []
    };
    const window = {
        dtcFetch: fetcher, dtcRequestFailure: error => errors.push(error),
        dtcConfirm: { open: options => { confirmation = options; } }
    };
    vm.runInNewContext(fs.readFileSync('public/js/notifications.js', 'utf8'), { document, window });
    return {
        button, elements, attributes, errors, get confirmation() { return confirmation; },
        click() {
            let prevented = false; let stopped = false;
            listener({ target: button, preventDefault: () => prevented = true, stopPropagation: () => stopped = true });
            assert.equal(capture, true); assert.equal(prevented, true); assert.equal(stopped, true);
        }
    };
}
const flush = () => new Promise(resolve => setImmediate(resolve));

test('read changes wait for the server, send CSRF, and suppress duplicate clicks', async () => {
    let resolve; const calls = [];
    const app = harness((url, options) => { calls.push({ url, options }); return new Promise(done => resolve = done); });
    app.click(); app.click();
    assert.equal(calls.length, 1);
    assert.equal(app.button.disabled, true);
    assert.equal(app.button.dataset.read, '0');
    assert.equal(app.elements['notif-badge'].textContent, '3');
    assert.equal(calls[0].options.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(calls[0].options.method, 'POST');
    assert.equal(JSON.parse(calls[0].options.body).read, true);
    resolve({ json: async () => ({ success: true, read: true, unread_count: 2 }) });
    await flush();
    assert.equal(app.button.dataset.read, '1');
    assert.equal(app.button.textContent, 'Mark unread');
    assert.equal(app.elements['notif-badge'].textContent, '2');
    assert.equal(app.button.disabled, false);
});

test('failed session or network response leaves read state and badge unchanged without retry', async () => {
    let attempts = 0;
    const app = harness(async () => { attempts++; throw new Error('Session expired'); });
    app.click(); await flush();
    assert.equal(attempts, 1);
    assert.equal(app.errors.length, 1);
    assert.equal(app.button.dataset.read, '0');
    assert.equal(app.elements['notif-badge'].textContent, '3');
    assert.equal(app.button.disabled, false);
    assert.equal(app.attributes['aria-busy'], undefined);
});

test('bulk read uses confirmed unread count and deletion requires confirmation', async () => {
    const app = harness(async () => ({ json: async () => ({ success: true, unread_count: 0 }) }), 'all');
    app.click(); await flush();
    assert.equal(app.elements['notif-badge'].hidden, true);
    assert.equal(app.elements['notif-mark-read'].hidden, true);
    let deletes = 0;
    const deletion = harness(async (url, options) => { deletes++; assert.equal(options.method, 'DELETE'); return { json: async () => ({ success: true, unread_count: 3 }) }; }, 'delete');
    deletion.click();
    assert.equal(deletes, 0);
    await deletion.confirmation.onConfirm();
    assert.equal(deletes, 1);
});
