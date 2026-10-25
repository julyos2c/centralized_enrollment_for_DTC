// ── DTC Nexus — Dark Mode ───────────────────────────────────────────────────
const DTC_THEME_KEY = "dtc-ems-theme";

function getDtcTheme() {
    let saved;
    try {
        saved = localStorage.getItem(DTC_THEME_KEY);
    } catch (error) {}
    if (saved === "dark" || saved === "light") return saved;
    return window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light";
}

function applyDtcTheme(theme) {
    const enabled = theme === "dark";
    document.documentElement.classList.toggle("dtc-dark", enabled);
    document.body.classList.toggle("dtc-dark", enabled);
    document.documentElement.classList.remove("dtc-dark-preload");

    const toggle = document.getElementById("darkModeToggle");
    const icon = document.getElementById("darkModeIcon");

    if (toggle) {
        toggle.setAttribute("aria-pressed", enabled ? "true" : "false");
        toggle.setAttribute(
            "aria-label",
            enabled ? "Enable light mode" : "Enable dark mode",
        );
        toggle.setAttribute(
            "title",
            enabled ? "Enable light mode" : "Enable dark mode",
        );
    }

    if (icon) {
        const replacement = document.createElement("i");
        replacement.id = "darkModeIcon";
        replacement.setAttribute("data-lucide", enabled ? "sun" : "moon");
        replacement.setAttribute("aria-hidden", "true");
        icon.replaceWith(replacement);
        if (window.lucide) lucide.createIcons();
    }

    // Let any page-specific code (e.g. Chart.js graphs) know the theme
    // changed so it can re-render with the right colors.
    document.dispatchEvent(
        new CustomEvent("dtc:themechange", {
            detail: { theme: theme, dark: enabled },
        }),
    );
}

function toggleDtcTheme() {
    const nextTheme = getDtcTheme() === "dark" ? "light" : "dark";
    try {
        localStorage.setItem(DTC_THEME_KEY, nextTheme);
    } catch (error) {}
    applyDtcTheme(nextTheme);
}

function initDtcSystemTheme() {
    const systemTheme = window.matchMedia("(prefers-color-scheme: dark)");
    function followSystem() {
        let saved;
        try {
            saved = localStorage.getItem(DTC_THEME_KEY);
        } catch (error) {}
        if (saved !== "dark" && saved !== "light") applyDtcTheme(getDtcTheme());
    }
    if (systemTheme.addEventListener)
        systemTheme.addEventListener("change", followSystem);
    else if (systemTheme.addListener) systemTheme.addListener(followSystem);
    // Keep open tabs consistent when the saved choice changes or is removed.
    window.addEventListener("storage", function (event) {
        if (event.key === DTC_THEME_KEY || event.key === null)
            applyDtcTheme(getDtcTheme());
    });
}

// ── Chart.js theming helper ─────────────────────────────────────────────
// Returns the current dark/light colors a Chart.js config should use so
// graphs match the rest of the UI (same palette as the CSS variables in
// dtc-theme.css). Call this when building chart options, and again inside
// a 'dtc:themechange' listener to restyle + update() existing charts.
function getDtcChartTheme() {
    const dark = document.body.classList.contains("dtc-dark");
    return {
        dark: dark,
        text: dark ? "#94A3B8" : "#94A3B8",
        grid: dark ? "rgba(148, 163, 184, 0.15)" : "#F1F5F9",
        tooltipBg: dark ? "#1E293B" : "#1E293B",
        tooltipText: dark ? "#F8FAFC" : "#F8FAFC",
        pointBorder: dark ? "#111827" : "#ffffff",
    };
}

// Registry so multiple charts on one page can all be restyled together.
window.dtcCharts = window.dtcCharts || [];

function registerDtcChart(chart) {
    window.dtcCharts.push(chart);
    return chart;
}

document.addEventListener("dtc:themechange", function () {
    const theme = getDtcChartTheme();
    window.dtcCharts.forEach(function (chart) {
        if (!chart || !chart.options) return;
        if (chart.options.scales) {
            ["x", "y"].forEach(function (axis) {
                if (chart.options.scales[axis]) {
                    if (chart.options.scales[axis].ticks)
                        chart.options.scales[axis].ticks.color = theme.text;
                    if (chart.options.scales[axis].grid)
                        chart.options.scales[axis].grid.color = theme.grid;
                }
            });
        }
        if (chart.data && chart.data.datasets) {
            chart.data.datasets.forEach(function (ds) {
                if (
                    Object.prototype.hasOwnProperty.call(ds, "pointBorderColor")
                ) {
                    ds.pointBorderColor = theme.pointBorder;
                }
            });
        }
        chart.update();
    });
});

// Apply the saved theme as early as possible.
applyDtcTheme(getDtcTheme());
initDtcSystemTheme();

document.addEventListener("click", function (event) {
    const toggle = event.target.closest("#darkModeToggle");
    if (!toggle) return;
    toggleDtcTheme();
});

// ── Progressive UX helpers ─────────────────────────────────────────────────
document.addEventListener(
    "submit",
    function (event) {
        const form = event.target;
        const spoofedMethod = form.querySelector('input[name="_method"]');
        if (
            spoofedMethod &&
            spoofedMethod.value.toUpperCase() === "DELETE" &&
            form.dataset.confirmed !== "true"
        )
            return;
        const submit = form.querySelector(
            "[data-loading-text], .dtc-loading-button",
        );
        if (!submit || form.dataset.submitting === "true") return;
        form.dataset.submitting = "true";
        submit.dataset.originalText = submit.innerHTML;
        submit.disabled = true;
        submit.classList.add("is-loading");
        submit.innerHTML =
            submit.getAttribute("data-loading-text") || "Processing…";
    },
    true,
);

// Delete confirmation — if the modal is available use it; otherwise fall back
// to window.confirm so behaviour is correct whether or not the feedback JS
// has already loaded.
document.addEventListener(
    "submit",
    function (event) {
        const form = event.target;
        const method = form.querySelector('input[name="_method"]');
        if (!method || method.value.toUpperCase() !== "DELETE") return;
        if (form.dataset.confirmed === "true") return;

        event.preventDefault();
        event.stopPropagation();

        if (!window.dtcConfirm) return;
        window.dtcConfirm.open({
            trigger: form.querySelector('[type="submit"]') || form,
            tone: "danger",
            title: form.dataset.confirmTitle || "Delete item?",
            message:
                form.dataset.confirmMessage || "This action cannot be undone.",
            okLabel: form.dataset.confirmOk || "Delete",
            onConfirm: function () {
                form.dataset.confirmed = "true";
                if (typeof form.requestSubmit === "function")
                    form.requestSubmit();
                else form.submit();
            },
        });
    },
    true,
);

// data-confirm legacy attribute: route to modal or native confirm
document.addEventListener(
    "click",
    function (event) {
        const trigger = event.target.closest("[data-confirm]");
        if (!trigger) return;
        // Skip elements that also have data-dtc-confirm (handled by dtc-feedback.js)
        if (trigger.hasAttribute("data-dtc-confirm")) return;
        if (!window.dtcConfirm) return;
        event.preventDefault();
        event.stopPropagation();
        window.dtcConfirm.open({
            trigger: trigger,
            tone: trigger.dataset.confirmTone || "neutral",
            title: trigger.dataset.confirmTitle || "Confirm action?",
            message:
                trigger.getAttribute("data-confirm") ||
                "Please confirm that you want to continue.",
            okLabel: trigger.dataset.confirmOk || "Confirm",
            onConfirm: function () {
                if (trigger.form) {
                    trigger.form.dataset.confirmed = "true";
                    if (typeof trigger.form.requestSubmit === "function")
                        trigger.form.requestSubmit();
                    else trigger.form.submit();
                } else if (trigger.href) window.location.href = trigger.href;
            },
        });
    },
    true,
);

document.addEventListener(
    "change",
    function (event) {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== "file")
            return;
        const label = input.closest(".dtc-file-upload");
        if (!label) return;
        const title = label.querySelector(".dtc-file-upload-title");
        if (title && input.files && input.files[0])
            title.textContent = input.files[0].name;
    },
    true,
);

// ── Global Search ─────────────────────────────────────────────────────────

const SUGGESTIONS = [
    "Dashboard",
    "Enrollment",
    "Appointments",
    "Payments",
    "Settings",
    "Users",
    "Notifications",
];
const STORAGE_KEY = "dtc_recent_searches";
let searchTimer = null;

// ── Storage helpers ───────────────────────────────────────────────────────
function getRecent() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
    } catch {
        return [];
    }
}

function addRecent(q) {
    if (!q || !q.trim()) return;
    let r = getRecent().filter((x) => x.toLowerCase() !== q.toLowerCase());
    r.unshift(q.trim());
    localStorage.setItem(STORAGE_KEY, JSON.stringify(r.slice(0, 5)));
}

function clearRecent() {
    localStorage.removeItem(STORAGE_KEY);
    renderRecent();
}

// ── Render helpers ────────────────────────────────────────────────────────
function renderSuggestions() {
    const el = document.getElementById("suggestions-list");
    if (!el) return;
    el.innerHTML = SUGGESTIONS.map(
        (s) => `
        <div class="search-suggestion-item"
             onclick="fillSearch('${s}')">
            <i data-lucide="search"></i>
            <span>${s}</span>
        </div>`,
    ).join("");
}

function renderRecent() {
    const list = document.getElementById("recent-list");
    const none = document.getElementById("no-recent");
    if (!list) return;

    const recent = getRecent();
    list.innerHTML = "";

    if (recent.length === 0) {
        if (none) none.style.display = "block";
        return;
    }

    if (none) none.style.display = "none";
    recent.forEach((r) => {
        const chip = document.createElement("button");
        chip.className = "search-recent-chip";
        chip.textContent = r;
        chip.onclick = () => fillSearch(r);
        list.appendChild(chip);
    });
}

function fillSearch(value) {
    const input = document.getElementById("global-search");
    if (input) {
        input.value = value;
        runSearch(value);
    }
}

// ── State managers ────────────────────────────────────────────────────────
function openSearch() {
    const dd = document.getElementById("search-dropdown");
    if (dd) dd.classList.add("open");
    renderSuggestions();
    renderRecent();

    const input = document.getElementById("global-search");
    if (input && !input.value.trim()) {
        showDefault();
    }
}

function closeSearch() {
    const dd = document.getElementById("search-dropdown");
    if (dd) dd.classList.remove("open");
}

function showDefault() {
    document.getElementById("search-default").style.display = "block";
    document.getElementById("search-loading").style.display = "none";
    document.getElementById("search-results").style.display = "none";
    document.getElementById("search-empty").style.display = "none";
}

function showLoading() {
    document.getElementById("search-default").style.display = "none";
    document.getElementById("search-loading").style.display = "block";
    document.getElementById("search-results").style.display = "none";
    document.getElementById("search-empty").style.display = "none";
}

function showResults(results) {
    document.getElementById("search-default").style.display = "none";
    document.getElementById("search-loading").style.display = "none";
    document.getElementById("search-results").style.display = "block";
    document.getElementById("search-empty").style.display = "none";

    const list = document.getElementById("results-list");
    list.replaceChildren();
    const safeUrl = (value, image = false) => {
        if (!value || value === "#") return image ? "" : "#";
        try {
            const url = new URL(String(value || ""), window.location.origin);
            return url.origin === window.location.origin &&
                ["http:", "https:"].includes(url.protocol)
                ? url.href
                : image
                  ? ""
                  : "#";
        } catch {
            return image ? "" : "#";
        }
    };
    const make = (tag, className, text) => {
        const element = document.createElement(tag);
        element.className = className;
        if (text !== undefined) element.textContent = String(text ?? "");
        return element;
    };
    results.forEach((r) => {
        const type = ["page", "user", "enrollment", "appointment"].includes(
            r.type,
        )
            ? r.type
            : "page";
        const link = make("a", "search-result-item");
        link.href = safeUrl(r.url);
        link.addEventListener("click", () => {
            addRecent(document.getElementById("global-search").value);
            closeSearch();
        });
        if (r.avatar) {
            const avatar = make("div", "search-result-avatar dtc-user-avatar");
            const fallback = make("span", "", r.avatar);
            fallback.setAttribute("aria-hidden", "true");
            avatar.appendChild(fallback);
            const src = safeUrl(r.avatar_url, true);
            if (src) {
                const img = make("img", "");
                img.src = src;
                img.alt = "Profile photo";
                img.addEventListener("error", () => {
                    img.hidden = true;
                });
                avatar.appendChild(img);
            }
            link.appendChild(avatar);
        } else {
            const icon = make("div", "search-result-icon " + type);
            const glyph = make("i", "");
            glyph.setAttribute(
                "data-lucide",
                /^[a-z-]+$/.test(r.icon || "") ? r.icon : "search",
            );
            icon.appendChild(glyph);
            link.appendChild(icon);
        }
        const body = make("div", "search-result-body");
        body.append(
            make("div", "search-result-title", r.title),
            make("div", "search-result-desc", r.desc),
        );
        link.append(body, make("span", "search-result-badge " + type, type));
        list.appendChild(link);
    });
    if (window.lucide)
        lucide.createIcons({ nodes: list.querySelectorAll("[data-lucide]") });
}

function showEmpty(query) {
    document.getElementById("search-default").style.display = "none";
    document.getElementById("search-loading").style.display = "none";
    document.getElementById("search-results").style.display = "none";
    document.getElementById("search-empty").style.display = "block";

    const sub = document.getElementById("empty-query");
    if (sub) sub.textContent = 'No results for "' + query + '"';
}

// ── Search runner ─────────────────────────────────────────────────────────
function runSearch(query) {
    if (!query || query.trim().length < 2) {
        showDefault();
        return;
    }

    showLoading();
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        window
            .dtcFetch("/search?q=" + encodeURIComponent(query.trim()), {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
            })
            .then((r) => r.json())
            .then((data) => {
                data.results.length > 0
                    ? showResults(data.results)
                    : showEmpty(query);
            })
            .catch((error) => {
                document.getElementById("search-loading").style.display =
                    "none";
                window.dtcRequestFailure(error);
            });
    }, 300);
}

function clearSearch() {
    const input = document.getElementById("global-search");
    const clear = document.getElementById("search-clear");
    const kbd = document.getElementById("search-kbd");

    if (input) input.value = "";
    if (clear) clear.classList.remove("visible");
    if (kbd) kbd.style.display = "flex";

    showDefault();
}

// ── Init ──────────────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("global-search");
    const clear = document.getElementById("search-clear");
    const kbd = document.getElementById("search-kbd");

    if (!input) return;

    // Input events
    input.addEventListener("focus", () => openSearch());

    input.addEventListener("input", function () {
        const q = this.value.trim();

        if (q.length > 0) {
            if (clear) clear.classList.add("visible");
            if (kbd) kbd.style.display = "none";
            runSearch(q);
        } else {
            if (clear) clear.classList.remove("visible");
            if (kbd) kbd.style.display = "flex";
            showDefault();
        }
    });

    input.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closeSearch();
            this.blur();
        }
        if (e.key === "Enter" && this.value.trim()) {
            addRecent(this.value.trim());
        }
    });

    // Ctrl+K
    document.addEventListener("keydown", function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === "k") {
            e.preventDefault();
            input.focus();
            openSearch();
        }
    });

    // Close on outside click
    document.addEventListener("click", function (e) {
        if (!e.target.closest(".navbar-search-item")) {
            closeSearch();
        }
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const searchWrapper = document.querySelector(".navbar-search-wrapper");
    const searchInput = document.querySelector("#global-search");

    if (!searchWrapper || !searchInput) return;

    searchWrapper.addEventListener("click", function (e) {
        if (window.innerWidth > 576) return;
        if (this.classList.contains("is-expanded")) return;
        e.stopPropagation();
        this.classList.add("is-expanded");
        setTimeout(function () {
            searchInput.focus();
            openSearch();
        }, 50);
    });

    // Mobile close is handled entirely by the outside-click listener below
    // (and the closeSearch() call in the first DOMContentLoaded block above).
    // A blur-based timeout close used to live here, but the input blurs as
    // soon as a suggestion/chip is tapped - well before that tap's own click
    // handler (fillSearch) runs and before its triggered search resolves -
    // so it was closing the dropdown out from under an in-flight search.

    searchInput.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        searchWrapper.classList.remove("is-expanded");
        searchInput.blur();
        closeSearch();
    });

    document.addEventListener("click", function (e) {
        if (window.innerWidth > 576) return;
        if (!e.target.closest(".navbar-search-item")) {
            searchWrapper.classList.remove("is-expanded");
        }
    });
});

// ── Sidebar profile menu toggle ────────────────────────────────────────────
// Replaces Bootstrap's collapse plugin for this specific widget.
// Using [hidden] attribute + a single delegated click listener avoids the
// open/close race that caused the 3-dot button to need multiple clicks
// (Bootstrap collapse fires on 'click', but mousedown on the toggle can
// blur focus and trigger an outside-click close in the same frame).
(function initSidebarProfileMenu() {
    function openMenu(toggle, menu) {
        menu.removeAttribute("hidden");
        toggle.setAttribute("aria-expanded", "true");
    }

    function closeMenu(toggle, menu) {
        menu.setAttribute("hidden", "");
        toggle.setAttribute("aria-expanded", "false");
    }

    document.addEventListener("DOMContentLoaded", function () {
        const toggle = document.getElementById("dtcSidebarProfileToggle");
        const menu = document.getElementById("dtcSidebarProfileMenu");
        if (!toggle || !menu) return;

        // Toggle on row click
        toggle.addEventListener("click", function (e) {
            e.stopPropagation();
            const isOpen = toggle.getAttribute("aria-expanded") === "true";
            isOpen ? closeMenu(toggle, menu) : openMenu(toggle, menu);
        });

        // Close when clicking anywhere outside the panel
        document.addEventListener("click", function (e) {
            if (!e.target.closest(".dtc-sidebar-user-panel")) {
                closeMenu(toggle, menu);
            }
        });

        // Close on Escape
        document.addEventListener("keydown", function (e) {
            if (
                e.key === "Escape" &&
                toggle.getAttribute("aria-expanded") === "true"
            ) {
                closeMenu(toggle, menu);
                toggle.focus();
            }
        });
    });
})();

// Payment copy controls are delegated because enrollment payment content may
// be loaded into a modal after the page has rendered.
(function initPaymentCopyControls() {
    function fallbackCopy(value) {
        var input = document.createElement("textarea");
        input.value = value;
        input.setAttribute("readonly", "");
        input.style.position = "fixed";
        input.style.opacity = "0";
        document.body.appendChild(input);
        input.select();
        var copied = document.execCommand("copy");
        input.remove();
        return copied;
    }

    document.addEventListener("click", function (event) {
        var button = event.target.closest("[data-copy-value]");
        if (!button) return;

        var value = button.getAttribute("data-copy-value") || "";
        var label = button.getAttribute("data-copy-label") || "Value";
        var textNode = button.querySelector("span");
        var original = textNode ? textNode.textContent : button.textContent;
        var showResult = function (success) {
            if (textNode)
                textNode.textContent = success ? "Copied" : "Copy failed";
            else button.textContent = success ? "Copied" : "Copy failed";
            button.setAttribute(
                "aria-label",
                success ? label + " copied" : "Could not copy " + label,
            );
            window.setTimeout(function () {
                if (textNode) textNode.textContent = original;
                else button.textContent = original;
                button.removeAttribute("aria-label");
            }, 1800);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(
                function () {
                    showResult(true);
                },
                function () {
                    showResult(fallbackCopy(value));
                },
            );
        } else {
            showResult(fallbackCopy(value));
        }
    });
})();

// ── Lucide icon init ───────────────────────────────────────────────────────
// dtc-app.js is loaded with `defer`, so the DOM is fully parsed by the time
// this runs. Call createIcons() here so icons on pages that use public.blade.php
// (which doesn't have the MutationObserver from master.blade.php) also render.
(function initDtcLucide() {
    if (window.lucide) {
        lucide.createIcons();
    } else {
        // Lucide CDN not yet evaluated (shouldn't happen with sync script tag,
        // but guard anyway)
        document.addEventListener("DOMContentLoaded", function () {
            if (window.lucide) lucide.createIcons();
        });
    }
})();
