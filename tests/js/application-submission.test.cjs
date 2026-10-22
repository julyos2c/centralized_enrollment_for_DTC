const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function setup() {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/application-wizard.js'), 'utf8');
    function element() {
        const listeners = {};
        return {
            value: '', disabled: false, listeners,
            classList: { add() {}, remove() {}, contains() { return false; } },
            addEventListener(type, fn) { (listeners[type] ||= []).push(fn); },
            fire(type, event) { (listeners[type] || []).forEach(fn => fn(event)); },
            setAttribute() {}, scrollIntoView() {}, closest() { return null; }
        };
    }
    const nodes = Object.fromEntries(['applicationForm', 'ack_enrollment'].map(id => [id, element()]));
    const button = element();
    button.click = () => button.fire('click');
    let submissions = 0;
    let valid = true;
    let navigated;
    let confirmation;
    const drafts = new Map([['draft', '{"first_name":"Juan"}']]);
    const pending = new Map();
    nodes.applicationForm.submit = () => submissions++;
    const document = { getElementById: id => nodes[id] || null, addEventListener() {} };
    const context = vm.createContext({ document, btnSubmit: button, STORAGE_KEY: 'draft', current: 6, TOTAL: 6,
        validateStep: () => valid, goTo: step => { navigated = step; },
        localStorage: { getItem: key => drafts.get(key), removeItem: key => drafts.delete(key) },
        sessionStorage: { setItem: (key, value) => pending.set(key, value) },
        window: { dtcConfirm: { open(options) { confirmation = options; } } }
    });
    const start = source.indexOf('    let submissionInProgress = false;');
    const end = source.indexOf('    const privacyModalEl');
    vm.runInContext(source.slice(start, end), context);
    const event = () => ({ prevented: false, preventDefault() { this.prevented = true; } });
    return { nodes, button, event, drafts, pending, confirmation: () => confirmation, submissions: () => submissions, invalidate: () => { valid = false; }, navigated: () => navigated };
}

test('Continue opens confirmation without acknowledging; final confirm submits once', () => {
    const s = setup();
    s.button.click();
    assert.equal(s.confirmation().okLabel, 'Submit');
    assert.equal(s.nodes.ack_enrollment.value, '');
    assert.equal(s.submissions(), 0);
    // Cancel/close does not invoke onConfirm; reopening still leaves acknowledgement blank.
    s.button.click();
    assert.equal(s.nodes.ack_enrollment.value, '');
    s.confirmation().onConfirm();
    assert.equal(s.nodes.ack_enrollment.value, '1');
    assert.equal(s.submissions(), 1);
    assert.equal(s.drafts.get('draft'), '{"first_name":"Juan"}');
    assert.equal(s.pending.get('dtc_pending_application_draft'), '{"first_name":"Juan"}');
    assert.equal(s.button.disabled, true);
    s.confirmation().onConfirm();
    s.button.click();
    assert.equal(s.submissions(), 1);
});

test('a failed form submission preserves its draft and is not automatically retried', () => {
    const s = setup();
    let attempts = 0;
    s.nodes.applicationForm.submit = () => { attempts++; throw new Error('Simulated network failure'); };
    s.button.click();
    assert.throws(() => s.confirmation().onConfirm(), /network failure/);
    assert.equal(s.drafts.get('draft'), '{"first_name":"Juan"}');
    s.button.click();
    assert.equal(attempts, 1);
});

test('server-confirmed success clears only the matching submitted draft', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/public/application/success.blade.php'), 'utf8');
    const start = source.indexOf('try {\n    const pendingDraft');
    const code = source.slice(start, source.indexOf('</script>', start));
    for (const current of ['submitted draft', 'new draft in another tab']) {
        const local = new Map([['dtc_application_draft', current]]);
        const session = new Map([['dtc_pending_application_draft', 'submitted draft']]);
        const storage = map => ({ getItem: key => map.has(key) ? map.get(key) : null, removeItem: key => map.delete(key) });
        vm.runInNewContext(code, { localStorage: storage(local), sessionStorage: storage(session) });
        assert.equal(local.has('dtc_application_draft'), current !== 'submitted draft');
    }
});

test('native submission opens confirmation without bypassing acknowledgement', () => {
    const s = setup();
    const event = s.event();
    s.nodes.applicationForm.fire('submit', event);
    assert.equal(event.prevented, true);
    assert.equal(s.confirmation().title, 'Submit application?');
    assert.equal(s.nodes.ack_enrollment.value, '');
    assert.equal(s.submissions(), 0);
});

test('invalid steps prevent confirmation and navigate to the incomplete section', () => {
    const s = setup();
    s.invalidate();
    s.button.click();
    assert.equal(s.confirmation(), undefined);
    assert.equal(s.navigated(), 1);
    assert.equal(s.submissions(), 0);
});
