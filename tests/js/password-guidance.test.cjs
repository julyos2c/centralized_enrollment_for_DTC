const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup({ admin = false, confirmation = true, minimum = 8, distinct = false, iconToggles = false, policy = null } = {}) {
    class Element {
        constructor(tag = 'DIV') { this.tagName = tag.toUpperCase(); this.children = []; this.events = {}; this.dataset = {}; this.attributes = {}; this.value = ''; this.type = 'password'; this.style = {}; }
        append(...children) { this.children.push(...children); }
        setAttribute(key, value) { this.attributes[key] = value; }
        getAttribute(key) { return this.attributes[key] || ''; }
        removeAttribute(key) { delete this.attributes[key]; }
        addEventListener(type, fn) { this.events[type] = fn; }
        setCustomValidity(value) { this.error = value; }
        insertAdjacentElement(position, element) { additions.push(element); }
        closest() { return field; }
        querySelector() { return !iconToggles; } // Existing visibility buttons are preserved.
    }
    const additions = [], field = new Element(), password = new Element('input'), confirm = confirmation ? new Element('input') : null;
    const current = distinct ? new Element('input') : null;
    password.minLength = minimum;
    [password, confirm, current].filter(Boolean).forEach(input => { input.parentElement = field; });
    const submit = new Element('button'); submit.type = 'submit'; submit.innerHTML = 'Save';
    const form = new Element('form');
    form.dataset = { passwordAccount: 'Test User' };
    form.hasAttribute = name => name === 'data-password-admin' ? admin : name === 'data-password-icon-toggles' ? iconToggles : name === 'data-password-distinct' && distinct;
    form.querySelector = selector => selector.includes('current_password') ? current : selector.includes('password_confirmation') ? confirm : selector.includes('name="password"') ? password : null;
    form.querySelectorAll = () => [submit];
    form.closest = () => null;
    form.checkValidity = () => password.value.length >= minimum && !password.error && (!confirm || (!!confirm.value && !confirm.error));
    form.reportValidity = () => {};
    form.requestSubmit = () => form.events.submit(event());
    const event = () => ({ prevented: false, preventDefault() { this.prevented = true; } });
    const pageEvents = {}, copies = [];
    let prompt;
    const window = { addEventListener: (name, fn) => { pageEvents[name] = fn; }, setTimeout: fn => fn(),
        crypto: { getRandomValues: bytes => bytes.fill(10) }, dtcConfirm: { open: options => { prompt = options; } } };
    vm.runInNewContext(fs.readFileSync('public/js/password-guidance.js', 'utf8'), {
        window, navigator: { clipboard: { writeText: async value => copies.push(value) } }, Uint8Array,
        document: { querySelectorAll: () => [form], createElement: tag => new Element(tag), getElementById: id => id === 'dtc-password-policy' && policy ? { dataset: policy } : null }
    });
    return { password, confirm, current, submit, form, additions, copies, pageEvents, event, prompt: () => prompt };
}

test('guidance matches real minimums, delays mismatch text until blur, and preserves matching feedback', () => {
    const app = setup();
    const [requirement, advice, match] = app.additions[0].children;
    assert.match(requirement.textContent, /8 characters/);
    app.password.value = 'longpassword'; app.password.events.input();
    app.confirm.value = 'long'; app.confirm.events.input();
    assert.equal(match.textContent, '');
    app.confirm.events.blur(); assert.equal(match.textContent, 'Passwords do not match');
    app.confirm.value = app.password.value; app.confirm.events.input();
    assert.equal(match.textContent, 'Passwords match');
    assert.equal(app.confirm.error, '');
    const short = setup({ admin: true, minimum: 6 });
    assert.match(short.additions[0].children[0].textContent, /6 characters/);
});

test('server policy updates guidance and generated passwords include required symbols', () => {
    const app = setup({ admin: true, policy: { minimum: '20', symbols: '1' } });
    assert.equal(app.password.minLength, 20);
    assert.match(app.additions[0].children[0].textContent, /20 characters/);
    assert.match(app.additions[0].children[1].textContent, /at least one symbol/);
    app.additions[0].children[3].children[0].events.click();
    assert.equal(app.password.value.length, 20);
    assert.match(app.password.value, /!/);
    assert.equal(app.confirm.value, app.password.value);
});

test('temporary password fields use accessible eye icons and toggle visibility independently', () => {
    const app = setup({ distinct: true, iconToggles: true });
    const toggles = app.additions.filter(element => element.className === 'pw-visibility');
    assert.equal(toggles.length, 3);
    for (const toggle of toggles) {
        assert.equal(toggle.getAttribute('aria-label'), 'Show password');
        assert.match(toggle.innerHTML, /<svg/);
        assert.equal(toggle.getAttribute('aria-pressed'), 'false');
    }
    toggles[1].events.click();
    assert.equal(app.password.type, 'text');
    assert.equal(app.current.type, 'password');
    assert.equal(app.confirm.type, 'password');
    assert.equal(toggles[1].getAttribute('aria-label'), 'Hide password');
    toggles[1].events.click();
    assert.equal(app.password.type, 'password');
    assert.equal(toggles[1].getAttribute('aria-pressed'), 'false');
});

test('admin generation fills both fields, copies only on request, and confirms before saving', async () => {
    const app = setup({ admin: true, minimum: 6 });
    const helper = app.additions[0];
    const [generate, copy] = helper.children[3].children;
    generate.events.click();
    assert.equal(app.password.value.length, 16);
    assert.equal(app.confirm.value, app.password.value);
    assert.equal(app.copies.length, 0);
    await copy.events.click(); assert.deepEqual(app.copies, [app.password.value]);
    const event = app.event(); app.form.events.submit(event);
    assert.equal(event.prevented, true);
    assert.equal(app.submit.disabled, undefined);
    assert.match(app.prompt().message, /Test User/);
    app.prompt().onConfirm(); assert.equal(app.submit.disabled, true);
    const duplicate = app.event(); app.form.events.submit(duplicate); assert.equal(duplicate.prevented, true);
    app.pageEvents.pageshow(); assert.equal(app.submit.disabled, undefined);
    assert.equal(app.submit.innerHTML, 'Save');
});

test('temporary password changes reject reuse, while normal login forms are ignored', () => {
    const app = setup({ distinct: true });
    app.current.value = app.password.value = 'temporary-password'; app.password.events.input();
    assert.match(app.password.error, /different/);
    app.password.value = 'a-different-password'; app.password.events.input(); assert.equal(app.password.error, '');
    const login = setup({ confirmation: false });
    assert.equal(login.additions.length, 0);
});
