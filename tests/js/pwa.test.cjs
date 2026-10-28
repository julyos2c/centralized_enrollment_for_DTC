const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function worker() {
    const handlers = {}, cached = new Map(), writes = [];
    const origin = 'https://college.example';
    let fail = false;
    const context = vm.createContext({ URL, Response,
        self: { registration: { scope: origin + '/ems/' }, location: { origin }, clients: { async claim() {} }, addEventListener(name, handler) { handlers[name] = handler; } },
        caches: { async open() { return { async put(url, response) { writes.push(url); cached.set(url, response); }, async match(url) { return cached.get(url); } }; }, async keys() { return []; } },
        async fetch(request) { if (fail) throw Error('offline'); return new Response('network response', { headers: { 'content-type': 'text/html' } }); },
    });
    vm.runInContext(fs.readFileSync('public/sw.js', 'utf8'), context);
    return {
        writes, offline() { fail = true; },
        async install() { let done; handlers.install({ waitUntil(promise) { done = promise; } }); await done; },
        async request(path, method = 'GET', mode = 'navigate') {
            let result;
            handlers.fetch({ request: { url: origin + path, method, mode }, respondWith(promise) { result = promise; } });
            return result ? (await result).clone().text() : null;
        },
    };
}

test('manifest has same-scope launch and real correctly sized installation icons', () => {
    const manifest = JSON.parse(fs.readFileSync('public/manifest.webmanifest', 'utf8'));
    assert.equal(manifest.display, 'standalone');
    assert.equal(manifest.start_url, './');
    assert.equal(manifest.scope, './');
    for (const size of [192, 512]) {
        const icon = manifest.icons.find(icon => icon.sizes === `${size}x${size}`);
        assert.ok(icon);
        const png = fs.readFileSync('public/' + icon.src);
        assert.equal(png.readUInt32BE(16), size);
        assert.equal(png.readUInt32BE(20), size);
    }
});

test('only the generic offline notice is cached, never authenticated pages or proof downloads', async () => {
    const app = worker();
    await app.install();
    for (const path of ['/ems/portal/dashboard', '/ems/set-password/secret', '/ems/portal/documents/1/proof']) {
        assert.equal(await app.request(path), 'network response');
    }
    assert.deepEqual(app.writes, ['https://college.example/ems/offline.html']);
    app.offline();
    assert.equal(await app.request('/ems/portal/dashboard'), 'network response'); // Cached public notice from installation.
    assert.equal(app.writes.length, 1);
});

test('payments, form submissions, API calls and assets are not intercepted or queued', async () => {
    const app = worker();
    app.offline();
    assert.equal(await app.request('/ems/portal/payments', 'POST'), null);
    assert.equal(await app.request('/ems/logout', 'POST'), null);
    assert.equal(await app.request('/ems/notifications/poll', 'GET', 'cors'), null);
    assert.equal(await app.request('/ems/css/dtc-theme.css', 'GET', 'no-cors'), null);
    assert.equal(app.writes.length, 0);
});

test('registration requires a secure context, same-origin worker and browser support', () => {
    const source = fs.readFileSync('public/js/pwa.js', 'utf8');
    for (const [secure, workerUrl, supported, expected] of [
        [true, 'https://college.example/ems/sw.js', true, 1],
        [false, 'https://college.example/ems/sw.js', true, 0],
        [true, 'https://other.example/sw.js', true, 0],
        [true, 'https://college.example/ems/sw.js', false, 0],
    ]) {
        let registrations = 0;
        vm.runInNewContext(source, { URL, location: { href: 'https://college.example/ems/', origin: 'https://college.example' },
            document: { documentElement: { classList: { add() {} } }, currentScript: { dataset: { worker: workerUrl } } },
            window: { setTimeout() {}, isSecureContext: secure, addEventListener(type, listener) { listener(); } },
            navigator: supported ? { serviceWorker: { register(url, options) { registrations++; assert.equal(options.scope, '/ems/'); return Promise.resolve(); } } } : {},
        });
        assert.equal(registrations, expected);
    }
});

test('startup dismisses on DOM readiness and restored pages even without service-worker support', () => {
    const events = {}, classes = new Set();
    let fallback;
    vm.runInNewContext(fs.readFileSync('public/js/pwa.js', 'utf8'), {
        document: { readyState: 'loading', currentScript: null,
            documentElement: { classList: { add(value) { classes.add(value); } } },
            addEventListener(name, callback) { events[name] = callback; } },
        window: { addEventListener(name, callback) { events[name] = callback; }, setTimeout(callback) { fallback = callback; } }
    });
    assert.equal(classes.has('dtc-pwa-ready'), false);
    events.DOMContentLoaded();
    assert.equal(classes.has('dtc-pwa-ready'), true);
    classes.clear();
    events.pageshow();
    assert.equal(classes.has('dtc-pwa-ready'), true);
    classes.clear();
    fallback();
    assert.equal(classes.has('dtc-pwa-ready'), true);
});
