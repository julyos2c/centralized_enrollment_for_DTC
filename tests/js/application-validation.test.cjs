const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

/** Minimal DOM node for the wizard's validation code. */
function node(props = {}) {
    const attributes = new Map();
    const classes = new Set();
    const children = [];
    return Object.assign({
        children, textContent: '', hidden: false,
        classList: { add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c) },
        setAttribute(k, v) { attributes.set(k, String(v)); },
        getAttribute(k) { return attributes.has(k) ? attributes.get(k) : null; },
        removeAttribute(k) { attributes.delete(k); },
        appendChild(child) { children.push(child); child.parent = this; return child; },
        insertBefore(child) { children.unshift(child); child.parent = this; return child; },
        replaceChildren() { children.length = 0; },
        addEventListener() {},
        remove() { if (this.parent) this.parent.children.splice(this.parent.children.indexOf(this), 1); },
        querySelector(selector) { return find(this, selector); },
        querySelectorAll() { return []; },
    }, props);
}
function find(root, selector) {
    for (const child of root.children || []) {
        const cls = selector.startsWith('.') ? selector.slice(1) : null;
        if (cls && child.className && child.className.split(' ').includes(cls)) return child;
        const dataFor = /\[data-for="(.+)"\]/.exec(selector);
        if (dataFor && child.getAttribute && child.getAttribute('data-for') === dataFor[1]) return child;
        if (selector === 'li' && child.tag === 'li') return child;
        const nested = find(child, selector);
        if (nested) return nested;
    }
    return null;
}

function setup() {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/application-wizard.js'), 'utf8');
    const body = node();
    const panel = node({ querySelector: s => (s === '.wiz-panel-body' ? body : find(body, s)) });
    const labels = {};
    function control(id, props = {}) {
        const field = node();
        const el = node(Object.assign({ id, name: id, value: '', required: false, disabled: false, type: 'text', tagName: 'INPUT', focused: false,
            validity: {}, field,
            closest(selector) {
                if (selector === '[hidden], .d-none') return this.inHidden ? {} : null;
                if (selector === '.wiz-field, .document-input') return field;
                if (selector === '.wiz-panel') return panel;
                return null;
            },
            focus() { this.focused = true; }, scrollIntoView() {} }, props));
        el.setAttribute('aria-describedby', 'existing-help');
        labels[id] = props.label || id;
        return el;
    }
    const first = control('first_name', { required: true, label: 'First Name *' });
    const last = control('last_name', { required: true, value: 'Cruz', label: 'Last Name *' });
    const gender = control('gender', { required: true, tagName: 'SELECT', label: 'Gender *' });
    const email = control('email', { type: 'email', value: 'juan@', validity: { typeMismatch: true }, label: 'Email Address' });
    const inactive = control('inactive', { required: true, disabled: true });
    const hiddenOther = control('education_status_other', { required: true, inHidden: true });
    panel.querySelectorAll = () => [first, last, gender, email, inactive, hiddenOther];
    const timers = [];
    const context = vm.createContext({
        TOTAL: 6, setTimeout: fn => timers.push(fn), window: {},
        document: {
            getElementById: id => (id === 'step-2' ? panel : null),
            querySelector: s => { const m = /label\[for="(.+)"\]/.exec(s); return m && labels[m[1]] ? { textContent: labels[m[1]] } : null; },
            createElement: tag => node({ tag }),
            createTextNode: text => ({ text }),
        },
        personalDetailsContent: null, setPersonalDetailsExpanded() {},
    });
    const start = source.indexOf('    /** The control the applicant sees');
    const end = source.indexOf('    document.querySelectorAll(".wiz-input, .document-input-file")');
    assert.ok(start > 0 && end > start, 'validation block markers must exist');
    vm.runInContext(source.slice(start, end), context);
    return { context, panel, body, first, last, gender, email, inactive, hiddenOther, timers, control };
}

test('a step with missing or invalid fields is blocked, each problem is shown, and the first field is focused', () => {
    const s = setup();
    assert.equal(vm.runInContext('validateStep(2)', s.context), false);
    s.timers.forEach(fn => fn());

    assert.equal(s.first.focused, true);
    assert.equal(s.first.getAttribute('aria-invalid'), 'true');
    assert.equal(s.first.getAttribute('aria-describedby'), 'existing-help first_name-error');
    const firstError = s.first.field.children[0];
    assert.equal(firstError.id, 'first_name-error');
    assert.match(firstError.children[1].text, /Enter first name\./);
    assert.match(s.gender.field.children[0].children[1].text, /Choose gender\./);
    assert.match(s.email.field.children[0].children[1].text, /valid email address/);
    // Disabled controls and fields in hidden sections are not checked.
    assert.equal(s.inactive.field.children.length, 0);
    assert.equal(s.hiddenOther.field.children.length, 0);
    assert.equal(s.last.getAttribute('aria-invalid'), null);

    // A list of what is missing appears at the top of the step.
    const alert = s.body.children[0];
    assert.equal(alert.className, 'wiz-step-alert');
    assert.match(alert.children[0].textContent, /3 items need your attention/);
    assert.deepEqual(alert.children[1].children.map(li => li.children[0].textContent), [
        'First Name: Enter first name.', 'Gender: Choose gender.', 'Email Address: Enter a valid email address, like name@example.com.',
    ]);
});

test('a silent check reports the result without marking fields', () => {
    const s = setup();
    assert.equal(vm.runInContext('validateStep(2, true)', s.context), false);
    assert.equal(s.first.getAttribute('aria-invalid'), null);
    assert.equal(s.body.children.length, 0);

    s.first.value = 'Juan';
    s.gender.value = 'female';
    s.email.value = 'juan@example.com';
    s.email.validity = {};
    assert.equal(vm.runInContext('validateStep(2, true)', s.context), true);
});

test('fixing a field clears its message and its entry in the list', () => {
    const s = setup();
    vm.runInContext('validateStep(2)', s.context);
    s.first.value = 'Juan';
    s.context.input = s.first;
    vm.runInContext('clearFieldError(input)', s.context);
    assert.equal(s.first.field.children.length, 0);
    assert.equal(s.first.getAttribute('aria-describedby'), 'existing-help');
    assert.equal(s.first.getAttribute('aria-invalid'), null);
    const items = s.body.children[0].children[1].children;
    assert.equal(items.length, 2);
    assert.equal(items.some(li => li.getAttribute('data-for') === 'first_name'), false);
});

test('uploads are checked for file type and size before moving on', () => {
    const s = setup();
    const upload = (name, size) => s.control('document_form_138', {
        type: 'file', files: [{ name, size }],
        getAttribute(k) { return { accept: '.pdf,.jpg', 'data-max-kb': '1024' }[k] ?? null; },
    });
    s.context.el = upload('report.gif', 100);
    assert.equal(vm.runInContext('fieldProblem(el)', s.context), 'Choose a PDF, JPG file.');
    s.context.el = upload('report.pdf', 2 * 1024 * 1024);
    assert.equal(vm.runInContext('fieldProblem(el)', s.context), 'This file is too large. The limit is 1 MB.');
    s.context.el = upload('report.PDF', 500 * 1024);
    assert.equal(vm.runInContext('fieldProblem(el)', s.context), '');
});
