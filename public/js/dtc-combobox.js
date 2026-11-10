/* DTC Nexus — searchable dropdown (ARIA 1.2 combobox with a listbox popup).
 *
 *   DtcCombobox.enhanceSelect(select)   type-to-filter for a native <select>;
 *                                       the select stays the submitted control.
 *   DtcCombobox.create({...})           free-text input with suggestions from
 *                                       an async source (e.g. school search).
 *
 * Keyboard: ↓/↑ move, Enter selects, Esc closes, Home/End jump; typing filters.
 */
(function () {
    "use strict";

    let uid = 0;

    function fold(text) {
        return String(text || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
    }

    /** Items whose label contains every typed word (accent/case-insensitive). */
    function filterItems(items, query) {
        const words = fold(query).split(/\s+/).filter(Boolean);
        if (!words.length) return items.slice();
        return items.filter(function (item) {
            const haystack = fold(item.label + " " + (item.keywords || ""));
            return words.every(function (w) { return haystack.indexOf(w) !== -1; });
        });
    }

    function Combobox(input, options) {
        this.input = input;
        this.options = options;
        this.items = [];
        this.visible = [];
        this.active = -1;
        this.requestSeq = 0;
        this.id = "dtc-cb-" + (++uid);

        const list = document.createElement("div");
        list.className = "dtc-cb-list";
        list.id = this.id + "-list";
        list.setAttribute("role", "listbox");
        list.hidden = true;
        if (options.label) list.setAttribute("aria-label", options.label);
        input.insertAdjacentElement("afterend", list);
        this.list = list;

        input.setAttribute("role", "combobox");
        input.setAttribute("aria-autocomplete", "list");
        input.setAttribute("aria-expanded", "false");
        input.setAttribute("aria-controls", list.id);
        input.setAttribute("autocomplete", "off");

        const self = this;
        input.addEventListener("input", function () { self.search(input.value); });
        input.addEventListener("focus", function () { if (options.openOnFocus) self.search(options.openOnFocus === "all" ? "" : input.value); });
        input.addEventListener("click", function () { if (options.openOnFocus && list.hidden) self.search(options.openOnFocus === "all" ? "" : input.value); });
        input.addEventListener("keydown", function (e) { self.keydown(e); });
        input.addEventListener("blur", function () {
            setTimeout(function () { self.close(); if (options.onBlur) options.onBlur(); }, 150);
        });
        // mousedown keeps focus in the input so blur does not close before click.
        list.addEventListener("mousedown", function (e) { e.preventDefault(); });
        list.addEventListener("click", function (e) {
            const node = e.target.closest("[role=option]");
            if (node) self.choose(Number(node.dataset.index));
        });
    }

    Combobox.prototype.search = function (query) {
        const self = this;
        if (this.options.enabled && !this.options.enabled()) {
            this.close();
            return;
        }
        const seq = ++this.requestSeq;
        const result = this.options.source(query);
        Promise.resolve(result).then(function (items) {
            if (seq !== self.requestSeq) return;
            self.render(items || [], query);
        }).catch(function () {
            if (seq !== self.requestSeq) return;
            self.render([], query, "Search is unavailable right now. You can keep typing.");
        });
    };

    Combobox.prototype.render = function (items, query, message) {
        const extras = this.options.extraItems ? this.options.extraItems(query) : [];
        this.visible = items.concat(extras);
        this.list.innerHTML = "";
        let group = null;
        const self = this;
        if (!this.visible.length) {
            const empty = document.createElement("div");
            empty.className = "dtc-cb-empty";
            empty.textContent = message || this.options.emptyText || "No matches";
            this.list.appendChild(empty);
        }
        this.visible.forEach(function (item, index) {
            if (item.group && item.group !== group) {
                group = item.group;
                const header = document.createElement("div");
                header.className = "dtc-cb-group";
                header.setAttribute("role", "presentation");
                header.textContent = group;
                self.list.appendChild(header);
            }
            const node = document.createElement("div");
            node.className = "dtc-cb-option" + (item.extra ? " is-extra" : "");
            node.id = self.id + "-opt-" + index;
            node.setAttribute("role", "option");
            node.setAttribute("aria-selected", "false");
            node.dataset.index = String(index);
            const label = document.createElement("span");
            label.className = "dtc-cb-label";
            label.textContent = item.label;
            node.appendChild(label);
            if (item.description) {
                const desc = document.createElement("span");
                desc.className = "dtc-cb-desc";
                desc.textContent = item.description;
                node.appendChild(desc);
            }
            self.list.appendChild(node);
        });
        this.open();
        this.setActive(this.visible.length ? 0 : -1);
    };

    Combobox.prototype.open = function () {
        this.list.hidden = false;
        this.input.setAttribute("aria-expanded", "true");
    };

    Combobox.prototype.close = function () {
        this.list.hidden = true;
        this.input.setAttribute("aria-expanded", "false");
        this.input.removeAttribute("aria-activedescendant");
        this.active = -1;
    };

    Combobox.prototype.setActive = function (index) {
        const nodes = this.list.querySelectorAll("[role=option]");
        nodes.forEach(function (n) { n.classList.remove("is-active"); n.setAttribute("aria-selected", "false"); });
        this.active = index;
        if (index < 0 || !nodes[index]) {
            this.input.removeAttribute("aria-activedescendant");
            return;
        }
        nodes[index].classList.add("is-active");
        nodes[index].setAttribute("aria-selected", "true");
        this.input.setAttribute("aria-activedescendant", nodes[index].id);
        nodes[index].scrollIntoView({ block: "nearest" });
    };

    Combobox.prototype.keydown = function (e) {
        const count = this.visible.length;
        if (e.key === "ArrowDown") {
            e.preventDefault();
            if (this.list.hidden) return this.search(this.options.openOnFocus === "all" ? "" : this.input.value);
            this.setActive(count ? (this.active + 1) % count : -1);
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            if (!this.list.hidden) this.setActive(count ? (this.active - 1 + count) % count : -1);
        } else if (e.key === "Home" && !this.list.hidden) {
            e.preventDefault();
            this.setActive(count ? 0 : -1);
        } else if (e.key === "End" && !this.list.hidden) {
            e.preventDefault();
            this.setActive(count - 1);
        } else if (e.key === "Enter") {
            if (!this.list.hidden && this.active >= 0) {
                e.preventDefault();
                this.choose(this.active);
            }
        } else if (e.key === "Escape") {
            if (!this.list.hidden) {
                e.preventDefault();
                this.close();
                if (this.options.onEscape) this.options.onEscape();
            }
        }
    };

    Combobox.prototype.choose = function (index) {
        const item = this.visible[index];
        if (!item) return;
        this.close();
        this.options.onSelect(item);
    };

    /** Type-to-filter for a native select; the select remains the form control. */
    function enhanceSelect(select, settings) {
        settings = settings || {};
        const field = select.closest(".wiz-field") || select.parentElement;
        const input = document.createElement("input");
        input.type = "text";
        input.className = select.className.replace(/\bdtc-cb-native\b/, "") + " dtc-cb-input";
        input.id = select.id + "_search";
        input.placeholder = settings.placeholder || "Type to search";
        input.setAttribute("spellcheck", "false");
        const label = field.querySelector('label[for="' + select.id + '"]');
        if (label) {
            if (!label.id) label.id = select.id + "_label";
            input.setAttribute("aria-labelledby", label.id);
        }
        select.classList.add("dtc-cb-native");
        select.setAttribute("tabindex", "-1");
        select.setAttribute("aria-hidden", "true");
        select.insertAdjacentElement("afterend", input);

        function items() {
            const out = [];
            Array.prototype.forEach.call(select.options, function (opt) {
                if (!opt.value || opt.disabled) return;
                const parent = opt.parentElement;
                out.push({
                    value: opt.value,
                    label: opt.textContent.trim(),
                    group: parent && parent.tagName === "OPTGROUP" ? parent.label : null,
                    keywords: opt.dataset.keywords || "",
                    extra: opt.dataset.extra === "1",
                });
            });
            return out;
        }

        function sync() {
            const opt = select.options[select.selectedIndex];
            input.value = opt && opt.value ? opt.textContent.trim() : "";
            input.disabled = select.disabled;
            input.required = select.required;
        }

        // Re-sync when another script rebuilds the options or changes the value.
        new MutationObserver(sync).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ["disabled"] });
        select.addEventListener("change", sync);

        const cb = new Combobox(input, {
            label: label ? label.textContent.trim() : "",
            openOnFocus: "all",
            emptyText: settings.emptyText || "No matches. Clear the text to see every option.",
            source: function (query) {
                const all = items();
                const current = select.options[select.selectedIndex];
                // Showing the chosen value should list everything, not only itself.
                if (current && current.value && query === current.textContent.trim()) return all;
                return filterItems(all, query);
            },
            onSelect: function (item) {
                select.value = item.value;
                select.dispatchEvent(new Event("change", { bubbles: true }));
                sync();
            },
            onEscape: sync,
            onBlur: sync,
        });
        sync();
        return { input: input, combobox: cb, sync: sync };
    }

    window.DtcCombobox = {
        create: function (input, options) { return new Combobox(input, options); },
        enhanceSelect: enhanceSelect,
        filterItems: filterItems,
    };
})();
