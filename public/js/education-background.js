/* DTC Nexus — education step: previous school (Bohol list, municipality
 * first), school location, SHS curriculum and strand, school years, and
 * completion status.
 *
 * Needs dtc-combobox.js and address-cascade.js (loaded first).
 * Every choice has an "Other" path; nothing forces an inaccurate option.
 */
(function () {
    "use strict";

    const DRAFT_KEY = "dtc_application_draft";
    const OTHER_YEAR = "__other";
    const NOT_LISTED = "__not_listed";
    const OUTSIDE = "__outside";

    /* Pure helpers (unit-tested). */
    function startYear(value) {
        const m = /^\s*(\d{4})\s*[-–—]\s*(\d{4})\s*$/.exec(value || "");
        return m && Number(m[2]) === Number(m[1]) + 1 ? Number(m[1]) : null;
    }

    /** Curricula a graduate of the given school year could have followed. */
    function curriculaForYear(curricula, year) {
        if (year === null) return curricula.slice();
        return curricula.filter(function (c) {
            return (c.first === null || year >= c.first) && (c.last === null || year <= c.last);
        });
    }

    /**
     * Strand options for a curriculum; strands matching the school's recorded
     * offerings come first. Returns [{group, value, label, extra}].
     */
    function strandOptions(curriculum, offerings) {
        if (!curriculum) return [];
        const offered = Array.isArray(offerings) ? offerings : [];
        const list = curriculum.classifications || [];
        const atSchool = offered.length ? list.filter(function (c) { return c.offering && offered.indexOf(c.offering) !== -1; }) : [];
        const options = atSchool.map(function (c) {
            return { group: "Offered by your school (DepEd list)", value: String(c.id), label: c.label };
        });
        list.forEach(function (c) {
            if (atSchool.indexOf(c) === -1) options.push({ group: c.track, value: String(c.id), label: c.name });
        });
        options.push({ group: "Other choices", value: "other", label: "My " + (curriculum.choice_label || "strand").toLowerCase() + " is not listed (type it)", extra: true });
        options.push({ group: "Other choices", value: "not_sure", label: "I don't remember my " + (curriculum.choice_label || "strand").toLowerCase(), extra: true });
        return options;
    }

    /**
     * School list options for a municipality ("" = whole province), grouped
     * by municipality, with one entry per school and the two manual choices last.
     */
    function schoolOptions(schools, localityCode, provinceName) {
        const seen = {};
        const options = [];
        schools
            .filter(function (s) { return !localityCode || s.city_code === localityCode; })
            .slice()
            .sort(function (a, b) {
                return (a.municipality || "~").localeCompare(b.municipality || "~") || a.name.localeCompare(b.name);
            })
            .forEach(function (s) {
                if (seen[s.id]) return;
                seen[s.id] = true;
                options.push({
                    group: s.municipality || "Municipality not recorded",
                    value: String(s.id),
                    label: localityCode ? s.name : s.name + (s.municipality ? " — " + s.municipality : ""),
                    keywords: (s.municipality || "") + " " + (s.deped_school_id || ""),
                });
            });
        options.push({ group: "Not in the list?", value: NOT_LISTED, label: "My school is not listed (type it)", extra: true });
        options.push({ group: "Not in the list?", value: OUTSIDE, label: "I studied outside " + (provinceName || "the province"), extra: true });
        return options;
    }

    window.DtcEducation = { startYear: startYear, curriculaForYear: curriculaForYear, strandOptions: strandOptions, schoolOptions: schoolOptions };

    if (typeof document === "undefined" || !document.getElementById) return;

    // application-wizard.js restores the draft (including the applicant type) on
    // DOMContentLoaded; this handler is registered after it, so it runs after it.
    function init() {
        const root = document.getElementById("educationFields");
        if (!root || !window.DtcCombobox || !window.DtcAddressCascade) return;

        function parse(json) {
            try {
                return JSON.parse(json || "null");
            } catch (e) {
                return null;
            }
        }
        function readDraft() {
            try {
                return JSON.parse(localStorage.getItem(DRAFT_KEY)) || {};
            } catch (e) {
                return {};
            }
        }
        function saveDraft(key, value) {
            try {
                const draft = readDraft();
                draft[key] = value;
                localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
            } catch (e) {}
        }
        const $ = function (id) { return document.getElementById(id); };

        const lookup = parse(root.dataset.lookup) || {};
        const old = parse(root.dataset.old) || {};
        const curricula = parse(root.dataset.curricula) || [];
        const pickerRoot = $("schoolPicker");
        const scope = pickerRoot ? parse(pickerRoot.dataset.scope) : null;
        const applicantType = $("academic_status");
        const isNewStudent = function () { return applicantType && applicantType.value === "new_student"; };

        const el = {
            status: $("education_status"), statusOtherField: $("educationStatusOtherField"), statusOther: $("education_status_other"),
            nameField: $("schoolNameField"), school: $("most_recent_school"), schoolId: $("school_id"),
            locality: $("school_locality"), schoolSelect: $("school_select"), choice: $("school_choice"), selectHint: $("schoolSelectHint"),
            sectorField: $("schoolSectorField"), sector: $("school_sector"),
            card: $("schoolSelectedCard"), change: $("schoolChangeButton"), street: $("school_street"),
            started: $("school_year_started"), startedOtherField: $("schoolYearStartedOtherField"), startedOther: $("school_year_started_other"),
            completed: $("school_year_completed"), completedOtherField: $("schoolYearCompletedOtherField"), completedOther: $("school_year_completed_other"),
            curriculum: $("shs_curriculum_id"), curriculumHint: $("shsCurriculumHint"),
            strand: $("shs_strand_selection"), strandOtherField: $("shsStrandOtherField"), strandText: $("shs_strand"),
        };

        /* ── "Other" specification fields ──────────────────────────────────── */
        function toggleOther(field, input, show) {
            field.hidden = !show;
            input.required = show;
            if (!show) input.classList.remove("wiz-invalid");
        }
        function syncStatus() {
            toggleOther(el.statusOtherField, el.statusOther, el.status.value === "Other");
        }
        el.status.addEventListener("change", syncStatus);
        syncStatus();

        [[el.started, el.startedOtherField, el.startedOther], [el.completed, el.completedOtherField, el.completedOther]].forEach(function (set) {
            const select = set[0];
            select.addEventListener("change", function () {
                toggleOther(set[1], set[2], select.value === OTHER_YEAR);
                if (select.value === OTHER_YEAR) set[2].focus();
            });
            toggleOther(set[1], set[2], select.value === OTHER_YEAR);
            window.DtcCombobox.enhanceSelect(select, { placeholder: "Type a year, e.g. 2024" });
        });

        /* ── School location picker (same PSGC lists as the home address) ── */
        const homeLookup = parse(($("addressFields") || {}).dataset ? $("addressFields").dataset.lookup : null) || {};
        const locationRoot = $("schoolLocationFields");
        const picker = locationRoot ? window.DtcAddressCascade.createPicker(locationRoot, {
            lookup: homeLookup,
            initial: {
                region_code: old.school_region_code || "",
                province_code: old.school_province_code || "",
                city_code: old.school_city_code || "",
                barangay: old.school_barangay || "",
            },
            draftKeys: { region: "school_region_code", area: "school_area", city: "school_city_code", barangay: "school_barangay" },
        }) : null;

        function setLocation(location) {
            if (picker) picker.ready.then(function () { picker.setLocation(location); });
        }

        /* ── Previous school ────────────────────────────────────────────────── */
        let schools = [];
        let selected = null;
        let choice = "";

        function describe(school) {
            return [
                school.deped_school_id ? "DepEd ID " + school.deped_school_id : "",
                school.sector_label || "",
                school.location || "",
            ].filter(Boolean).join(" · ");
        }

        function showCard(school) {
            el.card.hidden = !school;
            if (!school) return;
            el.card.querySelector('[data-school="name"]').textContent = school.name;
            el.card.querySelector('[data-school="meta"]').textContent = describe(school)
                + (school.offerings && school.offerings.length ? " · Offered: " + school.offerings.join(", ") : "");
        }

        /** Apply the visible state for the current choice (listed / not listed / outside). */
        function applyChoice() {
            const shs = isNewStudent();
            const manual = choice === NOT_LISTED || choice === OUTSIDE;
            // Other applicant types always type their school; SHS applicants only when it is not listed.
            el.nameField.hidden = shs && !!pickerRoot && !manual;
            el.sectorField.hidden = shs && choice === "listed";
            el.schoolId.value = choice === "listed" && selected ? selected.id : "";
            if (el.choice) el.choice.value = shs ? choice : "";
            showCard(choice === "listed" ? selected : null);
            // A school that is not on the list must be named (checked on the server too).
            el.school.required = shs && manual;
            if (shs && manual) {
                const label = el.nameField.querySelector("label");
                label.textContent = "Name of your Senior High School ";
                const mark = document.createElement("span");
                mark.className = "req";
                mark.textContent = "*";
                label.appendChild(mark);
            }
        }

        function chooseListed(school) {
            selected = school;
            choice = "listed";
            el.school.value = school.name;
            el.school.dispatchEvent(new Event("input", { bubbles: true }));
            if (school.sector) el.sector.value = school.sector;
            applyChoice();
            setLocation({
                region_code: school.region_code,
                province_code: school.province_code,
                city_code: school.city_code,
                barangay: school.barangay || "",
            });
            if (el.street && school.street_address && !el.street.value) el.street.value = school.street_address;
            rebuildStrands(true);
        }

        function chooseManual(kind, moveFocus) {
            const wasListed = choice === "listed";
            selected = null;
            choice = kind;
            if (wasListed) el.school.value = "";
            applyChoice();
            if (kind === OUTSIDE) {
                setLocation({ region_code: "" });
            } else if (scope) {
                setLocation({ region_code: scope.region_code, province_code: scope.province_code, city_code: el.locality.value || "" });
            }
            rebuildStrands(true);
            if (moveFocus) el.school.focus();
        }

        function renderSchools(keep) {
            const previous = keep ? el.schoolSelect.value : "";
            const options = schoolOptions(schools, el.locality.value, scope && scope.province_name);
            el.schoolSelect.innerHTML = "";
            const placeholder = document.createElement("option");
            placeholder.value = "";
            const count = options.length - 2;
            placeholder.textContent = count ? "Select your school (" + count + " listed)" : "No schools listed here — choose an option below";
            el.schoolSelect.appendChild(placeholder);
            let group = null;
            let groupNode = null;
            options.forEach(function (o) {
                if (o.group !== group) {
                    group = o.group;
                    groupNode = document.createElement("optgroup");
                    groupNode.label = group;
                    el.schoolSelect.appendChild(groupNode);
                }
                const node = document.createElement("option");
                node.value = o.value;
                node.textContent = o.label;
                node.dataset.keywords = o.keywords || "";
                if (o.extra) node.dataset.extra = "1";
                groupNode.appendChild(node);
            });
            el.schoolSelect.disabled = false;
            el.schoolSelect.value = previous;
            if (el.schoolSelect.value !== previous) el.schoolSelect.value = "";
            if (schoolCombo) schoolCombo.sync();
        }

        let schoolCombo = null;
        if (pickerRoot) {
            window.DtcCombobox.enhanceSelect(el.locality, { placeholder: "Type a municipality, e.g. Ubay" });
            schoolCombo = window.DtcCombobox.enhanceSelect(el.schoolSelect, {
                placeholder: "Type part of the school name",
                emptyText: "No listed school matches. Choose “not listed” to type it.",
            });

            el.locality.addEventListener("change", function () {
                saveDraft("school_locality", el.locality.value);
                // A listed school from another municipality no longer applies.
                if (choice === "listed" && selected && el.locality.value && selected.city_code !== el.locality.value) {
                    selected = null;
                    choice = "";
                    el.school.value = "";
                    applyChoice();
                }
                renderSchools(true);
                if (choice === NOT_LISTED) chooseManual(NOT_LISTED, false);
            });

            el.schoolSelect.addEventListener("change", function () {
                const value = el.schoolSelect.value;
                if (value === NOT_LISTED || value === OUTSIDE) {
                    chooseManual(value, true);
                } else if (value) {
                    const school = schools.find(function (s) { return String(s.id) === value; });
                    if (school) chooseListed(school);
                } else {
                    selected = null;
                    choice = "";
                    applyChoice();
                }
                saveDraft("school_choice", choice);
                saveDraft("school_selection", choice === "listed" ? selected : null);
            });

            el.change.addEventListener("click", function () {
                el.schoolSelect.value = "";
                el.schoolSelect.dispatchEvent(new Event("change", { bubbles: true }));
                const search = $("school_select_search");
                if (search) search.focus();
            });
        }

        /* ── Curriculum and strand ──────────────────────────────────────────── */
        let curriculumChosenByApplicant = !!old.shs_curriculum_id;
        const strandCombo = window.DtcCombobox.enhanceSelect(el.strand, { placeholder: "Type to search, e.g. STEM", emptyText: "No match. Choose “not listed” to type it." });

        function currentCurriculum() {
            return curricula.find(function (c) { return String(c.id) === el.curriculum.value; }) || null;
        }

        function rebuildStrands(keepValue) {
            const curriculum = currentCurriculum();
            const previous = keepValue ? el.strand.value : "";
            const options = strandOptions(curriculum, selected && selected.offerings);
            el.strand.innerHTML = "";
            const placeholder = document.createElement("option");
            placeholder.value = "";
            placeholder.textContent = curriculum ? "Select " + (curriculum.choice_label || "strand").toLowerCase() : "Select a curriculum first";
            el.strand.appendChild(placeholder);
            let group = null;
            let groupNode = null;
            options.forEach(function (o) {
                if (o.group !== group) {
                    group = o.group;
                    groupNode = document.createElement("optgroup");
                    groupNode.label = group;
                    el.strand.appendChild(groupNode);
                }
                const node = document.createElement("option");
                node.value = o.value;
                node.textContent = o.label;
                if (o.extra) node.dataset.extra = "1";
                groupNode.appendChild(node);
            });
            el.strand.disabled = !curriculum;
            el.strand.value = previous;
            if (el.strand.value !== previous) el.strand.value = "";
            document.querySelector('#step-4 label[for="shs_strand_selection"]').textContent =
                curriculum && curriculum.choice_label !== "Strand" ? "Track and " + curriculum.choice_label.toLowerCase() : "Track and strand";
            syncStrand();
            strandCombo.sync();
        }

        function syncStrand() {
            const value = el.strand.value;
            toggleOther(el.strandOtherField, el.strandText, value === "other");
            if (/^\d+$/.test(value)) {
                const option = el.strand.options[el.strand.selectedIndex];
                const curriculum = currentCurriculum();
                const item = curriculum && curriculum.classifications.find(function (c) { return String(c.id) === value; });
                el.strandText.value = item ? item.label : option.textContent;
            } else if (value === "not_sure") {
                el.strandText.value = "Not sure";
            } else if (value === "other" && /^(Not sure)?$/.test(el.strandText.value)) {
                el.strandText.value = "";
            }
            saveDraft("shs_strand_selection", value);
        }
        el.strand.addEventListener("change", function () {
            syncStrand();
            if (el.strand.value === "other") el.strandText.focus();
        });

        function suggestCurriculum() {
            if (curriculumChosenByApplicant) return;
            const year = startYear(el.completed.value === OTHER_YEAR ? el.completedOther.value : el.completed.value);
            const fits = curriculaForYear(curricula, year);
            if (year !== null && !fits.length) {
                el.curriculum.value = "";
                el.curriculumHint.textContent = "Senior High School began in SY 2016–2017. If you finished high school earlier, you can leave the strand blank.";
            } else {
                const pick = fits[0] || null;
                el.curriculum.value = pick ? String(pick.id) : "";
                el.curriculumHint.textContent = fits.length > 1
                    ? "Most graduates of this year followed the " + fits[0].name + ". Pilot schools used the " + fits[1].name + "; change it if yours did."
                    : "Chosen from your school year; change it if your school used another curriculum.";
            }
            rebuildStrands(true);
        }
        el.curriculum.addEventListener("change", function () {
            curriculumChosenByApplicant = el.curriculum.value !== "";
            saveDraft("shs_curriculum_choice", curriculumChosenByApplicant ? el.curriculum.value : "");
            rebuildStrands(false);
        });
        el.completed.addEventListener("change", suggestCurriculum);
        el.completedOther.addEventListener("input", suggestCurriculum);

        /* ── Applicant type changes (wizard enables whole sections) ─────────── */
        function syncApplicantType() {
            applyChoice();
            // The wizard re-enables every control in a shown section; restore picker states.
            if (picker) picker.refresh();
            el.strand.disabled = !currentCurriculum();
            strandCombo.sync();
        }
        if (applicantType) applicantType.addEventListener("change", syncApplicantType);

        /* ── Restore (server old input first, then the browser draft) ──────── */
        const draft = readDraft();
        if (old.shs_curriculum_id) {
            el.curriculum.value = String(old.shs_curriculum_id);
        } else if (draft.shs_curriculum_choice) {
            el.curriculum.value = draft.shs_curriculum_choice;
            curriculumChosenByApplicant = el.curriculum.value !== "";
        }
        if (!el.curriculum.value) suggestCurriculum(); else rebuildStrands(false);
        const wantedStrand = old.shs_strand_selection || draft.shs_strand_selection || "";
        if (wantedStrand) {
            el.strand.value = wantedStrand;
            syncStrand();
            strandCombo.sync();
        }

        const hadServerInput = !!(old.school || old.school_choice || old.most_recent_school || old.school_region_code);
        const restoredSchool = old.school || (!hadServerInput && draft.school_selection && draft.school_selection.name === el.school.value ? draft.school_selection : null);
        const restoredChoice = old.school ? "listed" : (hadServerInput ? old.school_choice : draft.school_choice) || "";
        if (pickerRoot && !hadServerInput && draft.school_locality) {
            el.locality.value = draft.school_locality;
        }
        if (restoredSchool) {
            selected = restoredSchool;
            choice = "listed";
            if (pickerRoot && restoredSchool.city_code && !el.locality.value) el.locality.value = restoredSchool.city_code;
        } else if (restoredChoice === NOT_LISTED || restoredChoice === OUTSIDE) {
            choice = restoredChoice;
        } else if (!pickerRoot || (hadServerInput && old.most_recent_school)) {
            // Older drafts / no Bohol scope: a typed school name.
            choice = el.school.value ? NOT_LISTED : "";
        }
        applyChoice();

        if (pickerRoot) {
            el.schoolSelect.disabled = true;
            fetch(lookup.schools, { headers: { Accept: "application/json" }, credentials: "same-origin" })
                .then(function (response) {
                    if (!response.ok) throw new Error("HTTP " + response.status);
                    return response.json();
                })
                .then(function (data) {
                    schools = data.results || [];
                    renderSchools(false);
                    el.schoolSelect.value = choice === "listed" && selected ? String(selected.id) : (choice || "");
                    if (schoolCombo) schoolCombo.sync();
                })
                .catch(function () {
                    // List unavailable: the applicant can still type the school name.
                    schools = [];
                    renderSchools(false);
                    el.selectHint.textContent = "The school list could not be loaded. Choose “not listed” and type your school.";
                });
            // Default school location: the province (Bohol), unless one is already set.
            if (picker && !old.school_region_code && !draft.school_region_code && choice !== OUTSIDE && !selected) {
                setLocation({ region_code: scope.region_code, province_code: scope.province_code });
            }
        }
        syncApplicantType();

        window.DtcEducation.summary = function () {
            const yearText = function (select, other) {
                return select.value === OTHER_YEAR ? other.value.trim() : select.value;
            };
            const optionText = function (select) {
                const o = select && select.options[select.selectedIndex];
                return o && o.value ? o.textContent.trim() : "";
            };
            const location = [
                el.street ? el.street.value.trim() : "",
                ($("school_barangay") || {}).value || "",
                optionText($("school_city_code")),
                optionText($("school_area_select")),
            ].filter(Boolean);
            return {
                status: el.status.value === "Other" ? "Other: " + el.statusOther.value.trim() : optionText(el.status),
                yearStarted: yearText(el.started, el.startedOther),
                yearCompleted: yearText(el.completed, el.completedOther),
                schoolLocation: location.join(", "),
                schoolType: selected ? (selected.sector_label || "") : optionText(el.sector),
                schoolId: selected && selected.deped_school_id ? selected.deped_school_id : "",
                curriculum: optionText(el.curriculum),
                strand: el.strand.value === "other" ? el.strandText.value.trim() : optionText(el.strand),
            };
        };
    }
    // Deferred scripts run while readyState is already "interactive", before
    // DOMContentLoaded fires, so wait for the event unless the page is complete.
    let started = false;
    function start() {
        if (started) return;
        started = true;
        init();
    }
    if (document.readyState === "complete") start();
    else if (typeof document.addEventListener === "function") {
        document.addEventListener("DOMContentLoaded", start);
        window.addEventListener("load", start);
    }
})();
