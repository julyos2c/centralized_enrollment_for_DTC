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
       WIZARD NAVIGATION
    ════════════════════════════════════════════════════════════ */

    function goTo(n, skipValidation) {
        if (!skipValidation && n > current && !validateStep(current)) return;
        current = Math.max(1, Math.min(TOTAL, n));

        panels.forEach(function (p, i) {
            p.classList.toggle("active", i + 1 === current);
        });
        steps.forEach(function (s, i) {
            s.classList.remove("active", "completed");
            if (i + 1 === current) s.classList.add("active");
            if (i + 1 < current) s.classList.add("completed");
            if (i + 1 === current) s.setAttribute("aria-current", "step");
            else s.removeAttribute("aria-current");
            s.disabled = false;
        });
        connectors.forEach(function (c, i) {
            c.classList.toggle("completed", i + 1 < current);
        });

        btnPrev.style.visibility = current === 1 ? "hidden" : "visible";

        if (current === TOTAL) {
            btnNext.classList.add("d-none");
            btnSubmit.classList.remove("d-none");
        } else {
            btnNext.classList.remove("d-none");
            btnSubmit.classList.add("d-none");
        }
        if (current === 6) buildReview();

        /* Rebuild each icon before Lucide renders it. Reusing a rendered SVG can
           leave the previous checkmark paths in place when returning to a step. */
        steps.forEach(function (s) {
            var dot = s.querySelector(".wiz-step-dot");
            if (!dot) return;
            var iconEl = document.createElement("i");
            var iconName = s.classList.contains("completed")
                ? "check"
                : s.getAttribute("data-original-icon");
            if (!iconName) return;
            iconEl.setAttribute("data-lucide", iconName);
            iconEl.setAttribute("aria-hidden", "true");
            dot.replaceChildren(iconEl);
        });

        if (window.lucide) lucide.createIcons();
        const heading = document.querySelector(
            "#step-" + current + " .wiz-panel-title",
        );
        if (heading) {
            heading.setAttribute("tabindex", "-1");
            heading.focus({ preventScroll: true });
        }
        const reduceMotion =
            window.matchMedia &&
            window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
    }

    /* ════════════════════════════════════════════════════════════
       VALIDATION
    ════════════════════════════════════════════════════════════ */

    function validateStep(n) {
        const panel = document.getElementById("step-" + n);
        if (!panel) return true;
        let ok = true;
        let firstInvalid = null;
        panel.querySelectorAll("[required]").forEach(function (el) {
            if (el.type === "hidden" || el.disabled) return;
            const field = el.closest(".wiz-field");
            if (!el.value.trim()) {
                el.classList.add("wiz-invalid");
                el.setAttribute("aria-invalid", "true");
                if (!firstInvalid) firstInvalid = el;
                if (field) {
                    let err = field.querySelector(".wiz-err-msg");
                    if (!err) {
                        err = document.createElement("span");
                        err.className = "wiz-err-msg wiz-err-dynamic";
                        err.innerHTML =
                            '<i data-lucide="alert-circle"></i> This field is required.';
                        field.appendChild(err);
                        if (window.lucide) lucide.createIcons({ nodes: [err] });
                    }
                    err.id = (el.id || el.name) + "-error";
                    const descriptions = (
                        el.getAttribute("aria-describedby") || ""
                    )
                        .split(/\s+/)
                        .filter(Boolean);
                    if (!descriptions.includes(err.id))
                        descriptions.push(err.id);
                    el.setAttribute("aria-describedby", descriptions.join(" "));
                }
                ok = false;
            } else {
                clearFieldError(el);
            }
        });
        if (firstInvalid)
            setTimeout(function () {
                firstInvalid.focus();
            }, 0);
        return ok;
    }

    function clearFieldError(el) {
        el.classList.remove("wiz-invalid");
        el.removeAttribute("aria-invalid");
        const field = el.closest(".wiz-field");
        const error = field && field.querySelector(".wiz-err-msg");
        if (error) {
            const descriptions = (el.getAttribute("aria-describedby") || "")
                .split(/\s+/)
                .filter(function (id) {
                    return id && id !== error.id;
                });
            if (descriptions.length)
                el.setAttribute("aria-describedby", descriptions.join(" "));
            else el.removeAttribute("aria-describedby");
            error.remove();
        }
    }

    document.querySelectorAll(".wiz-input").forEach(function (el) {
        const field = el.closest(".wiz-field");
        const error = field && field.querySelector(".wiz-err-msg");
        if (error) {
            error.id = (el.id || el.name) + "-error";
            el.setAttribute("aria-invalid", "true");
            const descriptions = (el.getAttribute("aria-describedby") || "")
                .split(/\s+/)
                .filter(Boolean);
            if (!descriptions.includes(error.id)) descriptions.push(error.id);
            el.setAttribute("aria-describedby", descriptions.join(" "));
        }
        function clear() {
            clearFieldError(el);
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
        /* Store the original icon name so goTo() can restore it when going back */
        var origI = s.querySelector(".wiz-step-dot [data-lucide]");
        if (origI)
            s.setAttribute(
                "data-original-icon",
                origI.getAttribute("data-lucide"),
            );

        s.addEventListener("click", function () {
            const target = i + 1;
            if (target === current) return;
            goTo(target, true);
        });
    });

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
            for (let step = 1; step < TOTAL; step++) {
                if (!validateStep(step)) {
                    goTo(step, true);
                    return;
                }
            }
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
                        document.getElementById("ack_enrollment").value = "1";
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

    /* ── Privacy policy modal ── */
    const privacyModalEl = document.getElementById("privacyModal");
    const closePrivacyModalBtn = document.getElementById("closePrivacyModal");
    const openPrivacyModalLink = document.getElementById("openPrivacyModal");

    if (privacyModalEl) {
        // Open modal when clicking Privacy Policy link
        if (openPrivacyModalLink) {
            openPrivacyModalLink.addEventListener("click", function (e) {
                e.preventDefault();
                privacyModalEl.classList.add("active");
            });
        }

        // Close modal when clicking "I Understand"
        if (closePrivacyModalBtn) {
            closePrivacyModalBtn.addEventListener("click", function () {
                privacyModalEl.classList.remove("active");
            });
        }

        // Close modal when clicking outside
        privacyModalEl.addEventListener("click", function (e) {
            if (e.target === privacyModalEl) {
                privacyModalEl.classList.remove("active");
            }
        });

        // Close modal on Escape key
        document.addEventListener("keydown", function (e) {
            if (
                e.key === "Escape" &&
                privacyModalEl.classList.contains("active")
            ) {
                privacyModalEl.classList.remove("active");
            }
        });
    }

    /* ── Terms modal ── */
    const termsModalEl = document.getElementById("termsModal");
    const closeTermsModalBtn = document.getElementById("closeTermsModal");
    const openTermsModalLink = document.getElementById("openTermsModal");

    if (termsModalEl) {
        // Open modal when clicking Terms link
        if (openTermsModalLink) {
            openTermsModalLink.addEventListener("click", function (e) {
                e.preventDefault();
                termsModalEl.classList.add("active");
            });
        }

        // Close modal when clicking "I Understand"
        if (closeTermsModalBtn) {
            closeTermsModalBtn.addEventListener("click", function () {
                termsModalEl.classList.remove("active");
            });
        }

        // Close modal when clicking outside
        termsModalEl.addEventListener("click", function (e) {
            if (e.target === termsModalEl) {
                termsModalEl.classList.remove("active");
            }
        });

        // Close modal on Escape key
        document.addEventListener("keydown", function (e) {
            if (
                e.key === "Escape" &&
                termsModalEl.classList.contains("active")
            ) {
                termsModalEl.classList.remove("active");
            }
        });
    }

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
            const city = document.getElementById("city_text")
                ? document.getElementById("city_text").value.trim()
                : "";
            const province = document.getElementById("province_text")
                ? document.getElementById("province_text").value.trim()
                : "";
            const region = document.getElementById("region_text")
                ? document.getElementById("region_text").value.trim()
                : "";
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
            if (postal && postal !== "—") addr += " " + postal;
            if (country && country !== "—") addr += ", " + country;
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
        const rawType = val("academic_status");
        const typeLabel = typeLabels[rawType] || rawType;

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
        const educationRows = [
            ["Education level", val("education_level")],
            ["Completion status", val("education_status")],
            [
                profile ? profile.school : "Most recent school",
                val("most_recent_school"),
            ],
            [
                profile
                    ? profile.year
                    : "School year completed or last attended",
                val("school_year_completed"),
            ],
            ["School location", val("school_location")],
        ];
        if (rawType === "new_student")
            educationRows.push([
                "Senior High School strand",
                val("shs_strand"),
            ]);
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
