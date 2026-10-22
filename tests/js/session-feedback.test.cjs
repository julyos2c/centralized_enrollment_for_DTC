const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('authentication and CSRF failures prevent successful AJAX continuations without retries', async () => {
    for (const status of [401, 403, 419, 500]) {
        const messages = [];
        let attempts = 0; let changed = false;
        const window = { dtcToast: { error: text => messages.push(text) } };
        vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/session-feedback.js'), 'utf8'), {
            window, fetch: async () => { attempts++; return { ok: false, status }; }
        });
        await window.dtcFetch('/notifications', { method: 'DELETE' })
            .then(() => { changed = true; }).catch(window.dtcRequestFailure);
        assert.equal(changed, false);
        assert.equal(attempts, 1);
        assert.equal(messages.length, 1);
        if (status === 419) assert.match(messages[0], /Reload/);
        if (status === 401) assert.match(messages[0], /Sign in/);
    }
});

test('a redirected login page is not accepted as successful modal HTML', () => {
    const window = { dtcToast: { error() {} } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/session-feedback.js'), 'utf8'), { window });
    assert.throws(() => window.dtcCheckResponse({ ok: true, redirected: true, url: 'https://example.test/login' }));
});
