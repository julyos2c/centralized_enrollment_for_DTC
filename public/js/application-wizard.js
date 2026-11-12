/**
 * DTC Nexus — Application Wizard
 *
 * Features:
 *  - Multi-step wizard navigation with per-step validation
 *  - localStorage persistence: form data survives page reload
 *  - Tab switching on Step 5 (Upload vs Requirements Checklist)
 *  - Interactive requirements checklist (checkboxes, not bullets)
 *  - Browser-assisted address entry with draft persistence
 *  - Optional free-text religion field
 */

document.addEventListener("DOMContentLoaded", function () {
    /* ════════════════════════════════════════════════════════════
       CONFIG
    ════════════════════════════════════════════════════════════ */

    const TOTAL = 6;
    const STORAGE_KEY = "dtc_application_draft";
    const CHECKS_KEY = "dtc_req_checks";
    let current = 1;

    /* ════════════════════════════════════════════════════════════
       ELEMENTS — WIZARD
    ════════════════════════════════════════════════════════════ */

    const panels = document.querySelectorAll(".wiz-panel");
    const steps = document.querySelectorAll(".wiz-step");
    const connectors = document.querySelectorAll(".wiz-connector");
    const btnPrev = document.getElementById("wizPrev");
    const btnNext = document.getElementById("wizNext");
    const btnSubmit = document.getElementById("wizSubmit");
    const tabBtns = document.querySelectorAll(".doc-tab-btn");
    const tabPanels = document.querySelectorAll(".doc-tab-panel");

    /* Step 1 / 5 — applicant reactive */
    const applicantType = document.getElementById("academic_status");
    const maritalStatus = document.getElementById("marital_status");
    const disability = document.getElementById("disability");
    const documentSections = document.querySelectorAll(".document-section");
    const educationSections = document.querySelectorAll(".education-section");
    const noDocumentNotice = document.getElementById("noDocumentNotice");
    const marriageDocument = document.getElementById("marriageDocument");
    const spouseField = document.getElementById("spouseField");
    const pwdIdField = document.getElementById("pwdIdField");
    const crossEnrolleeNotice = document.getElementById("crossEnrolleeNotice");
    const physicalRequirementsCard = document.getElementById(
        "physicalRequirementsCard",
    );
    const physicalRequirementsList = document.getElementById(
        "physicalRequirementsList",
    );

    /* Step 3 — current address cascade */
    /* Step 2 — place of birth cascade */

    /* ════════════════════════════════════════════════════════════
       PHYSICAL REQUIREMENTS DATA
    ════════════════════════════════════════════════════════════ */

    const physicalRequirements = {
        new_student: [
            "Form 138 or equivalent — original/required physical copy",
            "Certificate of Good Moral Character — original/required physical copy",
            "PSA Birth Certificate — required physical copy",
            "Marriage Certificate — if applicable",
            "2 copies recent 2×2 picture",
            "2 copies recent 1×1 picture",
            "2 pcs Long Brown Envelope",
        ],
        transferee: [
            "Certificate of Transfer Credentials",
            "Transcript of Records",
            "Certificate of Good Moral Character",
            "PSA Birth Certificate",
            "Marriage Certificate — if applicable",
            "2 copies recent 2×2 picture",
            "2 copies recent 1×1 picture",
            "2 pcs Long Brown Envelope",
        ],
        shiftee: [],
        returnee: [],
        cross_enrollee: [],
    };

    /* ════════════════════════════════════════════════════════════
       WIZARD NAVIGATION — strict, section by section

       A section counts as completed only after it passes the checks
       below and the server's check (POST /apply/steps/{n}), which
       validates it together with every earlier section and saves the
       answers in the applicant's session. Completed sections stay open
       for editing. The section after the last completed one is the
       furthest that can be opened; later sections are locked.
    ════════════════════════════════════════════════════════════ */

    const form = document.getElementById("applicationForm");
    const PROGRESS = window.dtcApplicationProgress || {};
    const STEP_TITLES = window.dtcApplicationStepTitles || {};
    const WIZARD_KEY = "dtc_application_wizard";
    const DATA_STEPS = TOTAL - 1;
    const STATE_LABELS = {
        completed: "Completed",
        "in-progress": "In progress",
        "not-started": "Not started",
        locked: "Locked",
    };
    let completed = Math.max(0, Math.min(DATA_STEPS, Number(PROGRESS.completed) || 0));
    let saving = false;
    let lastSavedAt = PROGRESS.saved_at || null;
    const stepMessage = document.getElementById("wizardStepMessage");
    const progressText = document.getElementById("wizardProgressText");
    const btnNextHtml = btnNext ? btnNext.innerHTML : "";

    function loadWizardState() {
        try {
            return JSON.parse(localStorage.getItem(WIZARD_KEY)) || {};
        } catch (e) {
            return {};
        }
    }
    let visited = (loadWizardState().visited || []).filter(function (n) {
        return typeof n === "number";
    });
    function saveWizardState() {
        try {
            localStorage.setItem(WIZARD_KEY, JSON.stringify({ step: current, visited: visited }));
        } catch (e) {}
    }

    function title(n) {
        return STEP_TITLES[n] || "this section";
    }
    function accessible(n) {
        return n >= 1 && n <= Math.min(TOTAL, completed + 1);
    }
    function stepState(n) {
        if (n <= completed) return "completed";
        if (!accessible(n)) return "locked";
        if (n === current || visited.indexOf(n) !== -1) return "in-progress";
        return "not-started";
    }

    function formatTime(iso) {
        const d = new Date(iso);
        if (isNaN(d.getTime())) return "";
        return d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
    }

    function renderTracker() {
        steps.forEach(function (s, i) {
            const n = i + 1;
            const state = stepState(n);
            s.classList.toggle("active", n === current);
            s.classList.toggle("completed", state === "completed");
            s.classList.toggle("locked", state === "locked");
            s.setAttribute("data-state", state);
            if (n === current) s.setAttribute("aria-current", "step");
            else s.removeAttribute("aria-current");
            if (state === "locked") s.setAttribute("aria-disabled", "true");
            else s.removeAttribute("aria-disabled");
            s.disabled = false;
            s.setAttribute("title", title(n) + ": " + STATE_LABELS[state]);
            let stateLabel = s.querySelector(".wiz-step-state");
            if (!stateLabel) {
                stateLabel = document.createElement("span");
                stateLabel.className = "wiz-step-state";
                s.appendChild(stateLabel);
            }
            stateLabel.textContent = STATE_LABELS[state];

            /* Rebuild each icon before Lucide renders it. Reusing a rendered SVG can
               leave the previous paths in place when the state changes. */
            const dot = s.querySelector(".wiz-step-dot");
            const iconName =
                state === "completed" ? "check" : state === "locked" ? "lock" : s.getAttribute("data-original-icon");
            if (!dot || !iconName) return;
            const iconEl = document.createElement("i");
            iconEl.setAttribute("data-lucide", iconName);
            iconEl.setAttribute("aria-hidden", "true");
            dot.replaceChildren(iconEl);
        });
        connectors.forEach(function (c, i) {
            c.classList.toggle("completed", i + 1 <= completed);
        });
        if (progressText) {
            const saved = lastSavedAt ? formatTime(lastSavedAt) : "";
            progressText.textContent =
                "Step " + current + " of " + TOTAL + ": " + title(current) +
                " · " + completed + " of " + DATA_STEPS + " sections completed" +
                (saved ? " · Progress saved " + saved : "");
        }
        if (window.lucide) lucide.createIcons();
    }

    function showMessage(text, tone) {
        if (!stepMessage) return;
        stepMessage.className = "wiz-step-message wiz-step-message--" + (tone || "info");
        stepMessage.replaceChildren();
        const icon = document.createElement("i");
        icon.setAttribute("data-lucide", tone === "error" ? "alert-circle" : "lock");
        icon.setAttribute("aria-hidden", "true");
        const span = document.createElement("span");
        span.textContent = text;
        stepMessage.appendChild(icon);
        stepMessage.appendChild(span);
        stepMessage.hidden = false;
        if (window.lucide) lucide.createIcons({ nodes: [stepMessage] });
    }
    function hideMessage() {
        if (stepMessage) stepMessage.hidden = true;
    }

    /** Display step n. Access rules are applied by goTo(). */
    function show(n) {
        current = Math.max(1, Math.min(TOTAL, n));
        if (visited.indexOf(current) === -1) visited.push(current);
        saveWizardState();

        panels.forEach(function (p, i) {
            p.classList.toggle("active", i + 1 === current);
        });
        btnPrev.style.visibility = current === 1 ? "hidden" : "visible";
        if (current === TOTAL) {
            btnNext.classList.add("d-none");
            btnSubmit.classList.remove("d-none");
        } else {
            btnNext.classList.remove("d-none");
            btnSubmit.classList.add("d-none");
        }
        if (current === TOTAL) {
            buildReview();
            updateSensitiveConsent();
        }
        renderTracker();

        const heading = document.querySelector("#step-" + current + " .wiz-panel-title");
        if (heading) {
            heading.setAttribute("tabindex", "-1");
            heading.focus({ preventScroll: true });
        }
        const reduceMotion =
            window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
    }

    /**
     * Open step n. Going back is always allowed and keeps every answer.
     * Going forward first checks and saves the current step; a step after
     * the next incomplete one is locked. skipValidation opens an accessible
     * step directly (page load, returning to a step with server errors).
     */
    function goTo(n, skipValidation) {
        n = Math.max(1, Math.min(TOTAL, n));
        if (saving) return Promise.resolve(false);
        if (n === current) {
            show(n);
            return Promise.resolve(true);
        }
        if (n < current) {
            leaveStep();
            hideMessage();
            show(n);
            return Promise.resolve(true);
        }
        // The step right after this one opens once this one is checked and saved.
        if (n > Math.max(completed + 1, current + 1) || (skipValidation && !accessible(n))) {
            showLocked(n);
            return Promise.resolve(false);
        }
        if (skipValidation) {
            hideMessage();
            show(n);
            return Promise.resolve(true);
        }
        return advanceTo(n);
    }

    function showLocked(n) {
        const next = Math.min(TOTAL, completed + 1);
        showMessage(
            "“" + title(n) + "” is locked. Complete “" + title(next) + "” first" +
                (next === current ? ", then select Next." : "."),
            "info",
        );
    }

    /** A completed step that no longer passes after edits is no longer complete. */
    function leaveStep() {
        if (current <= completed && current <= DATA_STEPS && !validateStep(current, true)) {
            completed = current - 1;
        }
    }

    function advanceTo(target) {
        hideMessage();
        if (!validateStep(current)) {
            if (!accessible(target)) showLocked(target);
            return Promise.resolve(false);
        }
        return saveStep(current).then(function (ok) {
            if (!ok) return false;
            if (target > completed + 1) {
                // An earlier section changed and is no longer complete.
                show(completed + 1);
                validateStep(current);
                showMessage("Complete “" + title(current) + "” before going to “" + title(target) + "”.", "error");
                return false;
            }
            show(target);
            return true;
        });
    }

    function setSaving(on) {
        saving = on;
        btnNext.disabled = on;
        if (on) {
            btnNext.setAttribute("aria-busy", "true");
            btnNext.textContent = "Saving…";
        } else {
            btnNext.removeAttribute("aria-busy");
            btnNext.innerHTML = btnNextHtml;
            if (window.lucide) lucide.createIcons({ nodes: [btnNext] });
        }
    }

    /** Server check and save of step n (with every earlier step). Resolves true on success. */
    function saveStep(n) {
        const template = form.getAttribute("data-step-url") || "";
        if (!template || typeof fetch !== "function" || typeof FormData !== "function") {
            // Very old browsers: the final submission still validates every section.
            completed = Math.max(completed, n);
            return Promise.resolve(true);
        }
        const data = new FormData(form);
        // Files are checked here in the browser and uploaded only on final submission.
        const fileKeys = [];
        data.forEach(function (value, key) {
            if (typeof File !== "undefined" && value instanceof File) fileKeys.push(key);
        });
        fileKeys.forEach(function (key) {
            data.delete(key);
        });

        setSaving(true);
        return fetch(template.replace(":step", String(n)), {
            method: "POST",
            body: data,
            credentials: "same-origin",
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        })
            .then(function (response) {
                return response
                    .json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (body) {
                        return { status: response.status, body: body || {} };
                    });
            })
            .then(function (res) {
                setSaving(false);
                if (res.status === 200) {
                    completed = Math.max(0, Math.min(DATA_STEPS, Number(res.body.completed) || n));
                    lastSavedAt = res.body.saved_at || lastSavedAt;
                    renderTracker();
                    return true;
                }
                if (res.status === 422 && res.body.errors) {
                    completed = Math.max(0, Math.min(completed, Number(res.body.completed) || 0));
                    const failed = Number(res.body.step) || n;
                    if (failed !== current) show(failed);
                    else renderTracker();
                    showServerErrors(res.body.errors);
                    if (failed !== n) showMessage(res.body.message || "Complete “" + title(failed) + "” first.", "error");
                    return false;
                }
                if (res.status === 419) {
                    showMessage(
                        "This form has expired. Reload the page to continue. Your answers are kept in this browser.",
                        "error",
                    );
                } else if (res.status === 429) {
                    showMessage("Too many attempts. Wait a minute, then select Next again.", "error");
                } else {
                    showMessage("We couldn't save this section. Please try again.", "error");
                }
                return false;
            })
            .catch(function () {
                setSaving(false);
                showMessage("We couldn't save this section. Check your internet connection, then select Next again.", "error");
                return false;
            });
    }

    /* ════════════════════════════════════════════════════════════
       VALIDATION
    ════════════════════════════════════════════════════════════ */

    /** The control the applicant sees (a searchable select shows a text box). */
    function visibleControl(el) {
        if (el.classList && el.classList.contains("dtc-cb-native")) {
            return document.getElementById(el.id + "_search") || el;
        }
        return el;
    }

    function fieldLabel(el) {
        const label = el.id ? document.querySelector('label[for="' + el.id + '"]') : null;
        let text = label ? label.textContent : el.getAttribute("aria-label") || el.name || "This field";
        text = text.replace(/\*/g, "").replace(/\bRequired\b/g, "").replace(/\s+/g, " ").trim();
        return text;
    }
    function lowerLabel(text) {
        return text
            .split(" ")
            .map(function (word) {
                return /^[A-Z0-9]{2,}$/.test(word) ? word : word.toLowerCase();
            })
            .join(" ");
    }

    function fileProblem(el) {
        if (!el.files || !el.files.length) return "";
        const file = el.files[0];
        const accept = (el.getAttribute("accept") || "")
            .split(",")
            .map(function (ext) {
                return ext.trim().toLowerCase();
            })
            .filter(Boolean);
        const ext = "." + String(file.name.split(".").pop() || "").toLowerCase();
        if (accept.length && accept.indexOf(ext) === -1) {
            return "Choose a " + accept.join(", ").toUpperCase().replace(/\./g, "") + " file.";
        }
        const maxKb = Number(el.getAttribute("data-max-kb")) || 0;
        if (maxKb && file.size > maxKb * 1024) {
            const limit = maxKb >= 1024 ? Math.round((maxKb / 1024) * 10) / 10 + " MB" : maxKb + " KB";
            return "This file is too large. The limit is " + limit + ".";
        }
        return "";
    }

    /** The problem with a field, or "" when it is fine. */
    function fieldProblem(el) {
        if (el.type === "file") return fileProblem(el);
        const value = String(el.value || "").trim();
        if (el.required && !value) {
            return (
                el.getAttribute("data-required-message") ||
                (el.tagName === "SELECT" ? "Choose " : "Enter ") + lowerLabel(fieldLabel(el)) + "."
            );
        }
        if (!value) return "";
        const v = el.validity;
        if (v) {
            if (v.typeMismatch && el.type === "email") return "Enter a valid email address, like name@example.com.";
            if (v.tooLong) return "Use no more than " + el.maxLength + " characters.";
            if (v.rangeOverflow) return el.id === "birthdate" ? "Date of birth can't be in the future." : "Enter an earlier date.";
            if (v.rangeUnderflow) return "Enter a later date.";
            if (v.badInput) return "Enter a valid date.";
            if (v.patternMismatch || v.typeMismatch) return el.getAttribute("title") || "Check the format of this field.";
        }
        return "";
    }

    function isShown(el) {
        return !el.disabled && el.type !== "hidden" && !(el.closest && el.closest("[hidden], .d-none"));
    }

    /** Checks that involve more than one field. Returns [[element, message], …]. */
    function crossFieldProblems(n) {
        const problems = [];
        if (n === 3) {
            const country = document.getElementById("country");
            const inPhilippines =
                !country ||
                (window.DtcAddressCascade && window.DtcAddressCascade.isPhilippines
                    ? window.DtcAddressCascade.isPhilippines(country.value)
                    : /^\s*(philippines)?\s*$/i.test(country.value));
            const region = document.getElementById("region_code");
            const area = document.getElementById("area_select");
            const city = document.getElementById("city_code");
            if (inPhilippines && region && region.value) {
                if (area && !area.disabled && area.options.length > 1 && !area.value) {
                    problems.push([area, "Choose your province or independent city, or clear the region."]);
                } else if (city && !city.value) {
                    problems.push([city, city.disabled
                        ? "Wait for the list of cities to load, then choose your city or municipality."
                        : "Choose your city or municipality, or clear the region."]);
                }
            }
        }
        return problems;
    }

    /**
     * Check step n. Shows each problem beside its field and a list at the top
     * of the step, then moves to the first one. silent: report only.
     */
    function validateStep(n, silent) {
        const panel = document.getElementById("step-" + n);
        if (!panel) return true;
        const problems = [];
        panel.querySelectorAll("input[name], select[name], textarea[name]").forEach(function (el) {
            if (!isShown(el) || el.type === "checkbox" || el.type === "radio") return;
            const message = fieldProblem(el);
            if (message) problems.push([el, message]);
            else if (!silent) clearFieldError(el);
        });
        crossFieldProblems(n).forEach(function (p) {
            problems.push(p);
        });
        if (silent) return problems.length === 0;

        problems.forEach(function (p) {
            markInvalid(p[0], p[1]);
        });
        renderStepAlert(panel, problems);
        if (problems.length) focusField(problems[0][0]);
        return problems.length === 0;
    }

    function revealField(el) {
        const details = el.closest && el.closest("details");
        if (details) details.open = true;
        if (personalDetailsContent && personalDetailsContent.contains(el)) setPersonalDetailsExpanded(true);
    }

    function focusField(el) {
        const target = visibleControl(el);
        revealField(target);
        setTimeout(function () {
            const reduceMotion =
                window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            if (target.scrollIntoView) target.scrollIntoView({ block: "center", behavior: reduceMotion ? "auto" : "smooth" });
            if (target.focus) target.focus({ preventScroll: true });
        }, 0);
    }

    function markInvalid(el, message) {
        const target = visibleControl(el);
        [el, target].forEach(function (node) {
            node.classList.add("wiz-invalid");
            node.setAttribute("aria-invalid", "true");
        });
        revealField(target);
        const field = target.closest(".wiz-field, .document-input");
        if (!field) return;
        let err = field.querySelector(".wiz-err-msg");
        if (!err) {
            err = document.createElement("span");
            err.className = "wiz-err-msg wiz-err-dynamic";
            field.appendChild(err);
        }
        err.replaceChildren();
        const icon = document.createElement("i");
        icon.setAttribute("data-lucide", "alert-circle");
        icon.setAttribute("aria-hidden", "true");
        err.appendChild(icon);
        err.appendChild(document.createTextNode(" " + message));
        if (window.lucide) lucide.createIcons({ nodes: [err] });
        err.id = (el.id || el.name) + "-error";
        const descriptions = (target.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean);
        if (descriptions.indexOf(err.id) === -1) descriptions.push(err.id);
        target.setAttribute("aria-describedby", descriptions.join(" "));
    }

    /** List of what is missing at the top of the step; each entry moves to its field. */
    function renderStepAlert(panel, problems) {
        const body = panel.querySelector(".wiz-panel-body") || panel;
        let alert = body.querySelector(".wiz-step-alert");
        if (!problems.length) {
            if (alert) alert.remove();
            return;
        }
        if (!alert) {
            alert = document.createElement("div");
            alert.className = "wiz-step-alert";
            alert.setAttribute("role", "alert");
            body.insertBefore(alert, body.firstChild);
        }
        alert.replaceChildren();
        const heading = document.createElement("p");
        heading.className = "wiz-step-alert-title";
        heading.textContent =
            problems.length === 1
                ? "1 item needs your attention before you can continue:"
                : problems.length + " items need your attention before you can continue:";
        const list = document.createElement("ul");
        problems.forEach(function (p) {
            const li = document.createElement("li");
            li.setAttribute("data-for", p[0].id || p[0].name);
            const button = document.createElement("button");
            button.type = "button";
            button.className = "wiz-step-alert-link";
            button.textContent = fieldLabel(p[0]) + ": " + p[1];
            button.addEventListener("click", function () {
                focusField(p[0]);
            });
            li.appendChild(button);
            list.appendChild(li);
        });
        alert.appendChild(heading);
        alert.appendChild(list);
    }

    function showServerErrors(errors) {
        const aliases = {
            school_id: "school_select",
            school_choice: "school_select",
            province_code: "area_select",
            school_province_code: "school_area_select",
        };
        const panel = document.getElementById("step-" + current);
        const problems = [];
        Object.keys(errors).forEach(function (key) {
            const message = [].concat(errors[key])[0];
            let el = aliases[key] ? document.getElementById(aliases[key]) : null;
            if (!el) {
                const name = key.indexOf("documents.") === 0 ? "documents[" + key.slice(10) + "]" : key;
                el = form.querySelector('[name="' + name + '"]');
            }
            if (!el || el.type === "hidden") el = panel ? panel.querySelector(".wiz-input") : null;
            if (el) problems.push([el, message]);
        });
        problems.forEach(function (p) {
            markInvalid(p[0], p[1]);
        });
        if (panel) renderStepAlert(panel, problems);
        if (problems.length) focusField(problems[0][0]);
    }

    function clearFieldError(el) {
        const target = visibleControl(el);
        [el, target].forEach(function (node) {
            node.classList.remove("wiz-invalid");
            node.removeAttribute("aria-invalid");
        });
        const field = target.closest(".wiz-field, .document-input");
        const error = field && field.querySelector(".wiz-err-msg");
        if (error) {
            const descriptions = (target.getAttribute("aria-describedby") || "").split(/\s+/).filter(function (id) {
                return id && id !== error.id;
            });
            if (descriptions.length) target.setAttribute("aria-describedby", descriptions.join(" "));
            else target.removeAttribute("aria-describedby");
            error.remove();
        }
        const alert = el.closest && el.closest(".wiz-panel") && el.closest(".wiz-panel").querySelector(".wiz-step-alert");
        if (alert) {
            const item = alert.querySelector('[data-for="' + (el.id || el.name) + '"]');
            if (item) item.remove();
            if (!alert.querySelector("li")) alert.remove();
        }
    }

    document.querySelectorAll(".wiz-input, .document-input-file").forEach(function (el) {
        const field = el.closest(".wiz-field");
        const error = field && field.querySelector(".wiz-err-msg");
        if (error) {
            error.id = (el.id || el.name) + "-error";
            el.setAttribute("aria-invalid", "true");
            const descriptions = (el.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean);
            if (!descriptions.includes(error.id)) descriptions.push(error.id);
            el.setAttribute("aria-describedby", descriptions.join(" "));
        }
        function clear() {
            if (el.classList.contains("wiz-invalid") || el.getAttribute("aria-invalid")) clearFieldError(el);
        }
        el.addEventListener("input", clear);
        el.addEventListener("change", clear);
    });

    btnNext.addEventListener("click", function () {
        goTo(current + 1);
    });
    btnPrev.addEventListener("click", function () {
        goTo(current - 1, true);
    });

    steps.forEach(function (s, i) {
        /* Store the original icon name so the tracker can restore it */
        const origI = s.querySelector(".wiz-step-dot [data-lucide]");
        if (origI) s.setAttribute("data-original-icon", origI.getAttribute("data-lucide"));

        s.addEventListener("click", function () {
            const target = i + 1;
            if (target === current || saving) return;
            goTo(target);
        });
    });

    /** Step to open on page load: where the applicant left off, if it is still open. */
    window._wizInitialStep = function () {
        const saved = Number(loadWizardState().step) || 0;
        if (PROGRESS.error_step) return Math.min(Number(PROGRESS.error_step), completed + 1);
        if (saved >= 1) return Math.min(saved, completed + 1);
        return completed > 0 ? completed + 1 : 1;
    };

    const screeningToggle = document.getElementById("screeningNoticeToggle");
    const screeningContent = document.getElementById("screeningNoticeContent");
    if (screeningToggle && screeningContent) {
        screeningToggle.addEventListener("click", function () {
            const expanded =
                screeningToggle.getAttribute("aria-expanded") === "true";
            screeningToggle.setAttribute("aria-expanded", String(!expanded));
            screeningContent.hidden = expanded;
        });
    }

    const personalDetailsToggle = document.getElementById(
        "personalDetailsToggle",
    );
    const personalDetailsContent = document.getElementById(
        "personalDetailsContent",
    );
    function setPersonalDetailsExpanded(expanded) {
        if (!personalDetailsToggle || !personalDetailsContent) return;
        personalDetailsToggle.setAttribute("aria-expanded", String(expanded));
        personalDetailsContent.hidden = !expanded;
    }
    if (personalDetailsToggle && personalDetailsContent) {
        personalDetailsToggle.addEventListener("click", function () {
            setPersonalDetailsExpanded(
                personalDetailsToggle.getAttribute("aria-expanded") !== "true",
            );
        });
    }

    /* ── Continue opens confirmation; acknowledgement is sent only on final submission ── */
    let submissionInProgress = false;

    /* Optional fields holding sensitive personal information (config/legal.php). */
    const SENSITIVE_FIELDS = {
        religion: "religion",
        marital_status: "civil status",
        spouse_name: "spouse's name",
        disability: "disability",
        pwd_id: "PWD ID",
    };
    function filledSensitiveFields() {
        return Object.keys(SENSITIVE_FIELDS).filter(function (name) {
            const field = document.getElementById(name);
            return field && !field.disabled && field.value.trim() !== "";
        });
    }
    function updateSensitiveConsent() {
        const wrap = document.getElementById("sensitiveConsentField");
        const box = document.getElementById("consent_sensitive");
        if (!wrap || !box) return;
        const filled = filledSensitiveFields();
        wrap.hidden = filled.length === 0;
        box.required = filled.length > 0;
        if (!filled.length) box.checked = false;
        const list = document.getElementById("sensitiveFieldList");
        if (list && filled.length) {
            list.textContent = filled.map(function (n) { return SENSITIVE_FIELDS[n]; }).join(", ");
        }
    }
    /** Each required box must be ticked by the applicant; show which one is missing. */
    function legalAcknowledgmentsComplete() {
        updateSensitiveConsent();
        const error = document.getElementById("legalAckError");
        const checks = [
            ["ack_enrollment", "Please agree to the Terms and Conditions."],
            ["acknowledge_privacy", "Please confirm that you have read the Privacy Notice."],
            ["consent_sensitive", "Give your consent for the optional sensitive details you entered, or go back and clear them."],
        ];
        for (let i = 0; i < checks.length; i++) {
            const box = document.getElementById(checks[i][0]);
            if (box && box.required && !box.checked) {
                if (error) {
                    error.textContent = checks[i][1];
                    error.hidden = false;
                }
                if (typeof box.focus === "function") box.focus();
                return false;
            }
        }
        if (error) error.hidden = true;
        return true;
    }
    Object.keys(SENSITIVE_FIELDS).forEach(function (name) {
        const field = document.getElementById(name);
        if (field && field.addEventListener) {
            field.addEventListener("input", updateSensitiveConsent);
            field.addEventListener("change", updateSensitiveConsent);
        }
    });
    document
        .getElementById("applicationForm")
        .addEventListener("submit", function (event) {
            event.preventDefault();
            if (submissionInProgress) return;
            if (current < TOTAL) goTo(current + 1);
            else btnSubmit.click();
        });

    if (btnSubmit) {
        btnSubmit.addEventListener("click", function () {
            if (submissionInProgress) return;
            // Every section must have been completed (and saved) first.
            if (completed < TOTAL - 1) {
                goTo(completed + 1, true);
                return;
            }
            for (let step = 1; step < TOTAL; step++) {
                if (!validateStep(step)) {
                    goTo(step, true);
                    return;
                }
            }
            if (!legalAcknowledgmentsComplete()) return;
            if (window.dtcConfirm) {
                window.dtcConfirm.open({
                    trigger: btnSubmit,
                    tone: "neutral",
                    title: "Submit application?",
                    message:
                        "Your application will be sent for review. Admission is not guaranteed.",
                    okLabel: "Submit",
                    onConfirm: function () {
                        if (submissionInProgress) return;
                        const form = document.getElementById("applicationForm");
                        if (!form) return;
                        submissionInProgress = true;
                        btnSubmit.disabled = true;
                        btnSubmit.setAttribute("aria-busy", "true");
                        try {
                            sessionStorage.setItem(
                                "dtc_pending_application_draft",
                                localStorage.getItem(STORAGE_KEY) || "",
                            );
                        } catch (ex) {}
                        form.submit();
                    },
                });
            }
        });
    }

    /* ── Policy document modals (Privacy Notice, Terms) ──
       Opening or closing a document never ticks an acknowledgment box and
       does not change the application. Without JS the links open the pages. */
    [
        ["privacyModal", "openPrivacyModal", "closePrivacyModal"],
        ["termsModal", "openTermsModal", "closeTermsModal"],
    ].forEach(function (ids) {
        const modal = document.getElementById(ids[0]);
        const opener = document.getElementById(ids[1]);
        const closer = document.getElementById(ids[2]);
        if (!modal || !opener) return;
        let returnFocus = null;
        function close() {
            modal.classList.remove("active");
            modal.hidden = true;
            if (returnFocus) returnFocus.focus();
        }
        opener.addEventListener("click", function (e) {
            e.preventDefault();
            returnFocus = opener;
            modal.hidden = false;
            modal.classList.add("active");
            const body = modal.querySelector(".privacy-modal-body");
            if (body) {
                body.scrollTop = 0;
                body.focus();
            }
        });
        if (closer) closer.addEventListener("click", close);
        modal.addEventListener("click", function (e) {
            if (e.target === modal) close();
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && !modal.hidden) close();
        });
    });

    /* ════════════════════════════════════════════════════════════
       FILE PICKER LABELS
    ════════════════════════════════════════════════════════════ */

    document.querySelectorAll(".document-input-file").forEach(function (input) {
        const wrap = input.closest(".document-input-control");
        const fn = wrap && wrap.querySelector(".document-input-filename");
        input.addEventListener("change", function () {
            if (!fn) return;
            if (input.files && input.files.length) {
                fn.textContent = input.files[0].name;
                wrap.classList.add("has-file");
            } else {
                fn.textContent = fn.dataset.placeholder || "No file chosen";
                wrap.classList.remove("has-file");
            }
        });
    });

    /* ════════════════════════════════════════════════════════════
       DOCUMENT TABS
    ════════════════════════════════════════════════════════════ */

    tabBtns.forEach(function (btn) {
        btn.addEventListener("click", function () {
            const t = btn.getAttribute("data-tab");
            tabBtns.forEach(function (b) {
                b.classList.toggle("active", b === btn);
            });
            tabPanels.forEach(function (p) {
                p.classList.toggle(
                    "active",
                    p.getAttribute("data-tab-panel") === t,
                );
            });
            if (window.lucide) lucide.createIcons();
        });
    });

    /* ════════════════════════════════════════════════════════════
       REQUIREMENTS CHECKLIST
    ════════════════════════════════════════════════════════════ */

    function loadChecks() {
        try {
            return JSON.parse(localStorage.getItem(CHECKS_KEY)) || {};
        } catch (e) {
            return {};
        }
    }
    function saveCheck(type, i, v) {
        const all = loadChecks();
        if (!all[type]) all[type] = {};
        all[type][i] = v;
        try {
            localStorage.setItem(CHECKS_KEY, JSON.stringify(all));
        } catch (e) {}
    }
    function buildChecklist(type) {
        const reqs = physicalRequirements[type] || [];
        const saved = loadChecks()[type] || {};
        physicalRequirementsList.innerHTML = "";
        if (!reqs.length) {
            physicalRequirementsCard.classList.add("d-none");
            return;
        }
        physicalRequirementsCard.classList.remove("d-none");
        reqs.forEach(function (text, i) {
            const id = "req_" + type + "_" + i;
            const li = document.createElement("li");
            const chk = document.createElement("input");
            chk.type = "checkbox";
            chk.className = "wiz-req-checkbox";
            chk.id = id;
            chk.checked = saved[i] === true;
            const lbl = document.createElement("label");
            lbl.className = "wiz-req-label";
            lbl.htmlFor = id;
            lbl.innerHTML =
                '<span class="wiz-req-box"></span><span class="wiz-req-text">' +
                text +
                "</span>";
            chk.addEventListener("change", function () {
                saveCheck(type, i, chk.checked);
            });
            li.appendChild(chk);
            li.appendChild(lbl);
            physicalRequirementsList.appendChild(li);
        });
    }

    /* ════════════════════════════════════════════════════════════
       APPLICANT-TYPE REACTIVE SECTIONS
    ════════════════════════════════════════════════════════════ */

    // Reuse stored field names so existing applications and drafts remain compatible.
    const educationProfiles = {
        new_student: {
            title: "Senior High School Background",
            description:
                "Provide information about the Senior High School where you graduated or last attended.",
            school: "Senior High School Name",
            year: "School Year Graduated or Last Attended",
            program: "Previous Degree Program",
            level: "Last Year Level",
            example: "e.g. Danao National High School",
        },
        transferee: {
            title: "Previous College Information",
            description:
                "Provide information about the college or university where you were last enrolled before applying for transfer.",
            school: "Previous College or University",
            year: "Last School Year Attended",
            program: "Previous Degree Program",
            level: "Last Year Level",
            example: "e.g. Cebu Normal University",
        },
        shiftee: {
            title: "Current Academic Information",
            description:
                "Provide information about your current or previous academic program before shifting to another program.",
            school: "Current College or University",
            year: "Current or Last School Year Attended",
            program: "Current or Previous Degree Program",
            level: "Current Year Level",
            example: "Enter your current institution",
        },
        returnee: {
            title: "Previous Enrollment Information",
            description:
                "Provide information about your last enrollment before taking a break from your studies.",
            school: "College or University Last Attended",
            year: "Last School Year Attended",
            program: "Previous Degree Program",
            level: "Last Year Level and Semester Attended",
            example: "Enter the institution where you were last enrolled",
        },
        cross_enrollee: {
            title: "Home Institution Information",
            description:
                "Provide information about your home institution and current studies.",
            school: "Most Recent School Attended",
            year: "Last School Year Attended",
            program: "Current Degree Program",
            level: "Current Year Level",
            example: "Enter your most recent school",
        },
    };

    function updateEducation(type) {
        updateEducationLevels(type);
        const profile = educationProfiles[type];
        if (!profile) return;
        document.getElementById("educationHeading").textContent = profile.title;
        document.getElementById("educationDescription").textContent =
            profile.description;
        const labels = {
            most_recent_school: profile.school,
            school_year_completed: profile.year,
            previous_program: profile.program,
            last_year_level: profile.level,
            education_status:
                type === "transferee"
                    ? "Enrollment or Completion Status"
                    : "Completion Status",
        };
        Object.keys(labels).forEach(function (name) {
            document.querySelector(
                '#step-4 label[for="' + name + '"]',
            ).textContent = labels[name];
        });
        document.getElementById("most_recent_school").placeholder =
            profile.example;
        document.getElementById("school_year_completed").placeholder =
            "e.g. 2025-2026";
        document.getElementById("previous_program").placeholder =
            "e.g. BS Information Systems";
        document.getElementById("last_year_level").placeholder =
            type === "returnee"
                ? "e.g. 2nd year, 1st semester"
                : "e.g. 2nd year";
        document
            .getElementById("last_year_level")
            .setAttribute("aria-describedby", "lastYearLevelHint");
    }

    function updateEducationLevels(type) {
        const select = document.getElementById("education_level");
        if (!select) return;
        const allowed =
            (window.dtcApplicationEducationLevels || {})[type] || [];
        const previous = select.value;
        select.replaceChildren();
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = type
            ? "Select education level"
            : "Choose applicant type first";
        select.appendChild(placeholder);
        allowed.forEach(function (level) {
            const option = document.createElement("option");
            option.value = level;
            option.textContent =
                level === "Other" ? "Other / equivalent qualification" : level;
            select.appendChild(option);
        });
        select.disabled = allowed.length === 0;
        select.value = allowed.includes(previous) ? previous : "";
        const hint = document.getElementById("educationLevelHint");
        if (hint)
            hint.textContent =
                previous && !allowed.includes(previous)
                    ? "Applicant type changed. Choose an education level again."
                    : type === "new_student"
                      ? "Use Other for an equivalent or older qualification."
                      : allowed.length
                        ? "Choose the level of your current or previous college studies."
                        : "Choose your applicant type in Admission first.";
        if (previous !== select.value) {
            select.dispatchEvent(new Event("change", { bubbles: true }));
        }
    }

    function updateApplicantType() {
        const t = applicantType.value;
        updateEducation(t);
        documentSections.forEach(function (s) {
            const visible = s.getAttribute("data-type") === t;
            s.classList.toggle("d-none", !visible);
            s.querySelectorAll('input[type="file"]').forEach(function (field) {
                field.disabled = !visible;
            });
        });
        noDocumentNotice.classList.toggle("d-none", !!t);
        crossEnrolleeNotice.classList.toggle("d-none", t !== "cross_enrollee");
        educationSections.forEach(function (section) {
            const types = (section.getAttribute("data-education-for") || "")
                .split(",")
                .map(function (value) {
                    return value.trim();
                });
            const visible = types.indexOf(t) !== -1;
            section.classList.toggle("d-none", !visible);
            section
                .querySelectorAll("input, select, textarea")
                .forEach(function (field) {
                    field.disabled = !visible;
                });
        });
        buildChecklist(t);
    }
    function updateMaritalStatus() {
        const m = maritalStatus.value === "married";
        spouseField.classList.toggle("d-none", !m);
        marriageDocument.classList.toggle("d-none", !m);
        marriageDocument
            .querySelectorAll('input[type="file"]')
            .forEach(function (field) {
                field.disabled = !m;
            });
        if (!m) document.getElementById("spouse_name").value = "";
    }
    function updateDisability() {
        pwdIdField.classList.toggle("d-none", disability.value.trim() === "");
    }

    applicantType.addEventListener("change", function () {
        updateApplicantType();
        saveField(applicantType);
    });
    maritalStatus.addEventListener("change", function () {
        updateMaritalStatus();
        saveField(maritalStatus);
    });
    disability.addEventListener("input", function () {
        updateDisability();
        saveField(disability);
    });

    document.getElementById("gender") &&
        document
            .getElementById("gender")
            .addEventListener("change", function () {
                saveField(document.getElementById("gender"));
            });

    /* Legacy PSGC codes remain blank; browser-autofilled address text uses normal draft persistence. */
    const LOC_NAMES = ["region_code", "province_code", "city_code"];

    const PERSIST_SELECTOR = [
        'input[type="text"]',
        'input[type="email"]',
        'input[type="date"]',
        'input[type="tel"]',
        "select",
        "textarea",
    ].join(", ");

    function loadDraft() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
        } catch (e) {
            return {};
        }
    }
    function saveDraft(d) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(d));
        } catch (e) {}
    }
    function saveField(el) {
        if (!el || !el.name) return;
        const d = loadDraft();
        d[el.name] = el.value;
        saveDraft(d);
    }

    function restoreDraft() {
        const draft = loadDraft();
        if (!Object.keys(draft).length) return;
        document.querySelectorAll(PERSIST_SELECTOR).forEach(function (el) {
            if (LOC_NAMES.indexOf(el.name) !== -1)
                return; /* handled by cascade */
            // Build the allowed options before assigning a saved select value.
            if (el.name === "education_level")
                updateEducationLevels(applicantType.value);
            // Preserve server-returned optional values, including an intentional blank.
            if (
                personalDetailsContent &&
                personalDetailsContent.contains(el) &&
                personalDetailsContent.dataset.hasOldInput === "true"
            )
                return;
            if (
                !el.name ||
                (el.value !== "" && el.value !== el.dataset.draftDefault)
            )
                return;
            if (draft[el.name] !== undefined) el.value = draft[el.name];
        });
    }

    document.querySelectorAll(PERSIST_SELECTOR).forEach(function (el) {
        if (LOC_NAMES.indexOf(el.name) !== -1) return;
        el.addEventListener("change", function () {
            saveField(el);
        });
        if (el.tagName === "INPUT" && el.type !== "date") {
            el.addEventListener("input", function () {
                saveField(el);
            });
        }
    });

    restoreDraft();

    const guardianDetails = document.getElementById("guardianDetails");
    if (
        guardianDetails &&
        Array.from(guardianDetails.querySelectorAll("input")).some(
            function (field) {
                return field.value.trim() !== "";
            },
        )
    ) {
        guardianDetails.open = true;
    }

    const countryInput = document.getElementById("country");
    const internationalField = document.getElementById(
        "internationalLocationField",
    );
    function updateInternationalAddress() {
        if (!internationalField) return;
        const country = countryInput
            ? countryInput.value.trim().toLocaleLowerCase()
            : "";
        internationalField.hidden = country === "" || country === "philippines";
    }
    if (countryInput)
        countryInput.addEventListener("input", updateInternationalAddress);
    updateInternationalAddress();
    const TITLE_CASE_FIELD_SET = new Set(
        window.dtcApplicationTitleCaseFields || [],
    );
    ["honorific", "suffix"].forEach(function (name) {
        const input = document.getElementById(name);
        if (!input) return;
        function formatAffix() {
            input.value = window.dtcNormalizeName.affix(input.value, name);
            saveField(input);
        }
        input.addEventListener("blur", formatAffix);
        input.addEventListener("change", formatAffix);
        formatAffix();
    });
    TITLE_CASE_FIELD_SET.forEach(function (name) {
        const input = document.querySelector('[name="' + name + '"]');
        if (!input) return;
        input.addEventListener("blur", function () {
            input.value = normalizeName(input.value);
            saveField(input);
        });
    });
    updateApplicantType();
    updateMaritalStatus();
    updateDisability();

    if (personalDetailsContent) {
        const hasPersonalDetails = Array.from(
            personalDetailsContent.querySelectorAll("input, select"),
        ).some(function (field) {
            const value = field.value.trim();
            return (
                value !== "" &&
                (field.name !== "nationality" ||
                    value.toLowerCase() !== "filipino")
            );
        });
        if (hasPersonalDetails) setPersonalDetailsExpanded(true);
    }

    window._wizGoTo = goTo;

    /* Open the first step: the page's hook decides (server errors), otherwise
       where the applicant left off. Nothing locked is ever opened. */
    if (typeof window._wizOnReady === "function") window._wizOnReady();
    else goTo(window._wizInitialStep(), true);

    /* ════════════════════════════════════════════════════════════
       STEP 6 — REVIEW PANEL
       Reads every named field from the form and renders a
       read-only summary so the applicant can verify before submitting.
    ════════════════════════════════════════════════════════════ */

    function normalizeName(value) {
        return window.dtcNormalizeName(value);
    }

    function buildReview() {
        const form = document.getElementById("applicationForm");
        if (!form) return;

        /* Helper: get display value for a field by name */
        function val(name) {
            const el =
                form.querySelector(
                    '[name="' + name + '"]:not([type="hidden"])',
                ) || form.querySelector('[name="' + name + '"]');
            if (!el) return "—";
            if (el.tagName === "SELECT") {
                const opt = el.options[el.selectedIndex];
                return opt && opt.text && opt.value ? opt.text : "—";
            }
            const value = el.value.trim();
            if (!value) return "—";
            if (name === "honorific" || name === "suffix")
                return window.dtcNormalizeName.affix(value, name);
            return TITLE_CASE_FIELD_SET.has(name)
                ? normalizeName(value)
                : value;
        }

        function nameVal(name) {
            const value = val(name);
            return value === "—" ? value : normalizeName(value);
        }

        /* Helper: get program name from select */
        function programName() {
            const sel = document.getElementById("program_id");
            if (!sel) return "—";
            const opt = sel.options[sel.selectedIndex];
            return opt && opt.value ? opt.text : "—";
        }

        /* Helper: assemble full address from hidden fields + plain fields */
        function fullAddress() {
            const street = val("current_address");
            const barangay = document.getElementById("barangay_text")
                ? document.getElementById("barangay_text").value.trim()
                : "";
            /* Names of the chosen address-picker options (codes are submitted). */
            function chosen(id, typeFilter) {
                const sel = document.getElementById(id);
                if (!sel || sel.disabled || !sel.value) return "";
                const opt = sel.options[sel.selectedIndex];
                if (typeFilter && opt.parentElement.tagName === "OPTGROUP"
                    && opt.parentElement.label !== typeFilter) return "";
                return opt ? opt.text.trim() : "";
            }
            const international = val("international_location");
            const city = chosen("city_code") || (international !== "—" ? international : "");
            const province = chosen("area_select", "Provinces");
            const region = chosen("region_code");
            const postal = val("postal_code");
            const country = val("country");

            const parts = [
                street,
                barangay ? "Brgy. " + barangay : "",
                city,
                province,
                region,
            ].filter(function (p) {
                return p && p !== "—";
            });
            let addr = parts.join(", ");
            if (postal && postal !== "—") addr = (addr ? addr + " " : "") + postal;
            // The country field defaults to "Philippines"; on its own that
            // default is not an address, so it is shown only with other details.
            if (country && country !== "—" && (addr || country.toLowerCase() !== "philippines"))
                addr = addr ? addr + ", " + country : country;
            return addr || "—";
        }

        /* Section builder */
        function section(title, icon, rows, step) {
            const filled = rows.filter(function (r) {
                return r[1] && r[1] !== "—";
            });
            const html = rows
                .map(function (r) {
                    const empty = !r[1] || r[1] === "—";
                    return (
                        '<div class="rev-row' +
                        (empty ? " rev-row--empty" : "") +
                        '">' +
                        '<span class="rev-label">' +
                        r[0] +
                        "</span>" +
                        '<span class="rev-value">' +
                        (empty ? "<em>Not provided</em>" : escHtml(r[1])) +
                        "</span>" +
                        "</div>"
                    );
                })
                .join("");
            return (
                '<div class="rev-section">' +
                '<div class="rev-section-head">' +
                '<i data-lucide="' +
                icon +
                '"></i>' +
                "<span>" +
                title +
                "</span>" +
                '<span class="rev-badge">' +
                filled.length +
                " / " +
                rows.length +
                "</span>" +
                '<button type="button" class="rev-edit" data-edit-step="' +
                step +
                '" aria-label="Edit ' +
                escHtml(title) +
                '">Edit</button>' +
                "</div>" +
                '<div class="rev-section-body">' +
                html +
                "</div>" +
                "</div>"
            );
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;");
        }

        /* Applicant type label map */
        const typeLabels = {
            new_student: "New Student / First Year",
            transferee: "Transferee",
            shiftee: "Shiftee",
            returnee: "Returnee / Readmitted",
            cross_enrollee: "Cross-Enrollee",
        };
        // val() returns a select's option text; type-specific rows need the value.
        const rawType = applicantType.value;
        const typeLabel = typeLabels[rawType] || val("academic_status");

        /* Assemble display full name */
        function fullName() {
            const honorific = val("honorific") !== "—" ? val("honorific") : "";
            const first =
                nameVal("first_name") !== "—" ? nameVal("first_name") : "";
            const middle =
                nameVal("middle_name") !== "—" ? nameVal("middle_name") : "";
            const last =
                nameVal("last_name") !== "—" ? nameVal("last_name") : "";
            const suffix = val("suffix") !== "—" ? val("suffix") : "";
            const mi = middle ? middle.charAt(0).toUpperCase() + "." : "";
            const name = [first, mi, last].filter(Boolean).join(" ");
            return [honorific, name, suffix].filter(Boolean).join(" ") || "—";
        }

        const profile = educationProfiles[rawType];
        // Structured answers (education-background.js) resolve "Other" choices.
        const edu = window.DtcEducation && window.DtcEducation.summary ? window.DtcEducation.summary() : null;
        const orDash = function (v) { return v ? v : "—"; };
        const educationRows = [
            ["Education level", val("education_level")],
            ["Completion status", edu ? orDash(edu.status) : val("education_status")],
            [
                profile ? profile.school : "Most recent school",
                val("most_recent_school"),
            ],
            ["School year started", edu ? orDash(edu.yearStarted) : "—"],
            [
                profile
                    ? profile.year
                    : "School year completed or last attended",
                edu ? orDash(edu.yearCompleted) : val("school_year_completed"),
            ],
            ["School location", rawType === "new_student" && edu ? orDash(edu.schoolLocation) : val("school_location")],
        ];
        if (rawType === "new_student") {
            if (edu && edu.schoolId) educationRows.push(["DepEd School ID", edu.schoolId]);
            educationRows.push(["Type of school", edu ? orDash(edu.schoolType) : "—"]);
            educationRows.push(["SHS curriculum", edu ? orDash(edu.curriculum) : "—"]);
            educationRows.push([
                "Senior High School strand",
                edu && edu.strand ? edu.strand : val("shs_strand"),
            ]);
        }
        if (["transferee", "shiftee", "returnee"].indexOf(rawType) !== -1) {
            educationRows.push([profile.program, val("previous_program")]);
            educationRows.push([profile.level, val("last_year_level")]);
        }
        if (rawType === "cross_enrollee") {
            educationRows.push(["Home institution", val("home_institution")]);
            educationRows.push([
                "Current course or program",
                val("home_program"),
            ]);
            educationRows.push([
                "Last year level or semester attended",
                val("last_year_level"),
            ]);
        }

        const documentRows = [];
        form.querySelectorAll('input[type="file"]').forEach(function (input) {
            if (input.disabled || !input.files || !input.files.length) return;
            const label = input
                .closest(".document-input")
                ?.querySelector('label[for="' + input.id + '"]');
            documentRows.push([
                label ? label.textContent.trim() : "Supporting document",
                input.files[0].name,
            ]);
        });
        if (!documentRows.length)
            documentRows.push(["Supporting documents", "No files selected"]);

        const html =
            section(
                "Admission Details",
                "graduation-cap",
                [
                    ["Applicant Type", typeLabel],
                    ["Intended Program", programName()],
                ],
                1,
            ) +
            section(
                "Personal Information",
                "user",
                [
                    ["Full Name", fullName()],
                    ["Gender", val("gender")],
                    ["Date of Birth", val("birthdate")],
                    [
                        "Age",
                        (function () {
                            const dob = val("birthdate");
                            if (!dob || dob === "—") return "—";
                            const today = new Date();
                            const birthParts = dob.split("-").map(Number);
                            const birth = new Date(
                                birthParts[0],
                                birthParts[1] - 1,
                                birthParts[2],
                            );
                            let age = today.getFullYear() - birth.getFullYear();
                            const m = today.getMonth() - birth.getMonth();
                            if (
                                m < 0 ||
                                (m === 0 && today.getDate() < birth.getDate())
                            )
                                age--;
                            return age > 0 ? age + " years old" : "—";
                        })(),
                    ],
                    ["Place of Birth", val("birth_place")],
                    ["Religion", val("religion")],
                    ["Nationality", val("nationality")],
                    ["LRN", val("lrn")],
                    ["Marital Status", val("marital_status")],
                    ["Spouse Name", nameVal("spouse_name")],
                ],
                2,
            ) +
            section(
                "Contact & Address",
                "map-pin",
                [
                    ["Email", val("email")],
                    ["Phone", val("phone")],
                    ["Address", fullAddress()],
                ],
                3,
            ) +
            section(
                profile ? profile.title : "Educational Background",
                "school",
                educationRows,
                4,
            ) +
            section(
                "Family Information",
                "users",
                [
                    ["Guardian's Name", nameVal("guardian_name")],
                    ["Guardian's Contact", val("guardian_contact")],
                    ["Father's Name", nameVal("father_name")],
                    ["Father's Occupation", val("father_occupation")],
                    ["Mother's Name", nameVal("mother_name")],
                    ["Mother's Occupation", val("mother_occupation")],
                    ["Parent's Address", val("parent_address")],
                    ["Parent's Contact", val("parent_contact")],
                    ["Occupation", val("occupation")],
                    ["Disability", val("disability")],
                    ["PWD ID", val("pwd_id")],
                ],
                5,
            ) +
            section("Supporting Documents", "files", documentRows, 5);

        const container = document.getElementById("reviewSummary");
        if (container) {
            container.innerHTML = html;
            container
                .querySelectorAll("[data-edit-step]")
                .forEach(function (button) {
                    button.addEventListener("click", function () {
                        goTo(
                            Number(button.getAttribute("data-edit-step")),
                            true,
                        );
                    });
                });
            if (window.lucide) lucide.createIcons({ nodes: [container] });
        }
    }
});
