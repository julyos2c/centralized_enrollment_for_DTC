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
    const nodes = Object.fromEntries(['applicationForm', 'ack_enrollment', 'acknowledge_privacy'].map(id => [id, element()]));
    // Required, applicant-ticked checkboxes (Terms agreement, Privacy acknowledgment).
    for (const id of ['ack_enrollment', 'acknowledge_privacy']) Object.assign(nodes[id], { required: true, checked: true, value: '1', focus() {} });
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
    const end = source.indexOf('    /* ── Policy document modals');
    vm.runInContext(source.slice(start, end), context);
    const event = () => ({ prevented: false, preventDefault() { this.prevented = true; } });
    return { nodes, button, event, drafts, pending, confirmation: () => confirmation, submissions: () => submissions, invalidate: () => { valid = false; }, navigated: () => navigated };
}

test('Continue opens confirmation only when both boxes are ticked; final confirm submits once', () => {
    const s = setup();
    s.button.click();
    assert.equal(s.confirmation().okLabel, 'Submit');
    assert.equal(s.submissions(), 0);
    // Cancel/close does not invoke onConfirm; the script never changes the boxes.
    s.button.click();
    s.confirmation().onConfirm();
    assert.equal(s.nodes.ack_enrollment.checked, true);
    assert.equal(s.nodes.acknowledge_privacy.checked, true);
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
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/public/application/success.blade.php'), 'utf8').replace(/\r\n/g, '\n');
    const start = source.indexOf('try {\n    const pendingDraft');
    assert.notEqual(start, -1, 'Draft cleanup script must exist');
    const end = source.indexOf('</script>', start);
    assert.notEqual(end, -1, 'Draft cleanup script must close');
    const code = source.slice(start, end);
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
    assert.equal(s.submissions(), 0);
});

test('an unticked Terms or Privacy box blocks submission and is never ticked for the applicant', () => {
    for (const id of ['ack_enrollment', 'acknowledge_privacy']) {
        const s = setup();
        s.nodes[id].checked = false;
        s.button.click();
        assert.equal(s.confirmation(), undefined, id + ' must be ticked');
        assert.equal(s.nodes[id].checked, false);
        assert.equal(s.submissions(), 0);
    }
});

test('invalid steps prevent confirmation and navigate to the incomplete section', () => {
    const s = setup();
    s.invalidate();
    s.button.click();
    assert.equal(s.confirmation(), undefined);
    assert.equal(s.navigated(), 1);
    assert.equal(s.submissions(), 0);
});
