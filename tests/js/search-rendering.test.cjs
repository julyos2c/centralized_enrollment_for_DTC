const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    const nodes = {};
    function element(tag = 'div') {
        return { tag, children: [], style: {}, attributes: {}, listeners: {},
            set innerHTML(value) { throw new Error('Search must not parse HTML: ' + value); },
            setAttribute(key, value) { this.attributes[key] = value; },
            addEventListener(key, fn) { this.listeners[key] = fn; },
            appendChild(child) { this.children.push(child); },
            append(...children) { this.children.push(...children); },
            replaceChildren() { this.children = []; }, querySelectorAll() { return []; }
        };
    }
    ['search-default', 'search-loading', 'search-results', 'search-empty', 'results-list'].forEach(id => { nodes[id] = element(); });
    const source = fs.readFileSync('public/js/dtc-app.js', 'utf8');
    const start = source.indexOf('function showResults(');
    const end = source.indexOf('function showEmpty(', start);
    assert.ok(start >= 0 && end > start);
    const context = vm.createContext({ URL, window: { location: { origin: 'https://dtc.test' } },
        document: { createElement: element, getElementById: id => nodes[id] } });
    vm.runInContext(source.slice(start, end), context);
    return { render: data => context.showResults(data), list: nodes['results-list'] };
}

test('search treats markup in every result label as literal text', () => {
    const s = setup();
    const markup = '<img src=x onerror=alert(1)><b>Student</b>';
    s.render([{ type: 'user', title: markup, desc: '</div><script>alert(1)</script>', avatar: markup, url: '/profile', avatar_url: '/photo.jpg' }]);
    const link = s.list.children[0];
    assert.equal(link.href, 'https://dtc.test/profile');
    assert.equal(link.children[0].children[0].textContent, markup);
    assert.equal(link.children[1].children[0].textContent, markup);
    assert.equal(link.children[1].children[1].textContent, '</div><script>alert(1)</script>');
    const photo = link.children[0].children[1];
    photo.listeners.error();
    assert.equal(photo.hidden, true);
});

test('search rejects executable and external URLs and untrusted CSS/icon attributes', () => {
    for (const url of ['javascript:alert(1)', 'data:text/html,test', '//evil.test/x', '#', undefined]) {
        const s = setup();
        s.render([{ type: 'user', title: 'Safe', avatar: 'A', url, avatar_url: url }]);
        assert.equal(s.list.children[0].href, '#');
        assert.equal(s.list.children[0].children[0].children.length, 1);
        s.render([{ type: 'x" onclick="test', icon: 'x" onload="test', url: '/safe' }]);
        const link = s.list.children[0];
        assert.equal(link.children[0].className, 'search-result-icon page');
        assert.equal(link.children[0].children[0].attributes['data-lucide'], 'search');
    }
});
