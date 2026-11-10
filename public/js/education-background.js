/* DTC Nexus — education step: previous school lookup, school location,
 * SHS curriculum and strand, school years, and completion status.
 *
 * Needs dtc-combobox.js and address-cascade.js (loaded first).
 * Every choice has an "Other" path; nothing forces an inaccurate option.
 */
(function () {
    "use strict";

    const DRAFT_KEY = "dtc_application_draft";
    const OTHER_YEAR = "__other";

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

    window.DtcEducation = { startYear: startYear, curriculaForYear: curriculaForYear, strandOptions: strandOptions };

    if (typeof document === "undefined" || !document.getElementById) return;
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
    const applicantType = $("academic_status");
    const isNewStudent = function () { return applicantType && applicantType.value === "new_student"; };

    const el = {
        status: $("education_status"), statusOtherField: $("educationStatusOtherField"), statusOther: $("education_status_other"),
        school: $("most_recent_school"), schoolId: $("school_id"), schoolHint: $("schoolSearchHint"),
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

    /* ── Previous school lookup ─────────────────────────────────────────── */
    let selected = null;

    function describe(school) {
        return [
            school.deped_school_id ? "DepEd ID " + school.deped_school_id : "",
            school.sector_label || "",
            school.location || "",
        ].filter(Boolean).join(" · ");
    }

    function showSchool(school) {
        selected = school;
        el.schoolId.value = school ? school.id : "";
        el.card.hidden = !school;
        el.sectorField.hidden = !!school;
        if (school) {
            el.card.querySelector('[data-school="name"]').textContent = school.name;
            el.card.querySelector('[data-school="meta"]').textContent = describe(school)
                + (school.offerings && school.offerings.length ? " · Offered: " + school.offerings.join(", ") : "");
            if (school.sector) el.sector.value = school.sector;
        }
        saveDraft("school_selection", school || null);
        rebuildStrands(true);
    }

    function chooseSchool(school) {
        el.school.value = school.name;
        el.school.dispatchEvent(new Event("input", { bubbles: true }));
        showSchool(school);
        if (picker && school.region_code) {
            picker.setLocation({
                region_code: school.region_code,
                province_code: school.province_code,
                city_code: school.city_code,
                barangay: school.barangay || "",
            });
        }
        if (el.street && school.street_address && !el.street.value) el.street.value = school.street_address;
    }

    const schoolCombobox = window.DtcCombobox.create(el.school, {
        label: "Matching schools",
        enabled: isNewStudent,
        emptyText: "No listed school matches.",
        source: function (query) {
            const term = (query || "").trim();
            if (term.length < 2 || !lookup.schools) return [];
            return fetch(lookup.schools + "?q=" + encodeURIComponent(term), {
                headers: { Accept: "application/json" }, credentials: "same-origin",
            }).then(function (response) {
                if (!response.ok) throw new Error("HTTP " + response.status);
                return response.json();
            }).then(function (data) {
                return (data.results || []).map(function (school) {
                    return { value: String(school.id), label: school.name, description: describe(school), school: school };
                });
            });
        },
        extraItems: function (query) {
            const term = (query || "").trim();
            if (term.length < 2) return [];
            return [{ value: "manual", label: "My school is not listed — use “" + term + "”", extra: true, group: "Not in the list?" }];
        },
        onSelect: function (item) {
            if (item.value === "manual") {
                showSchool(null);
                el.sector.focus();
            } else {
                chooseSchool(item.school);
            }
        },
    });

    el.school.addEventListener("input", function (event) {
        // Editing the name after picking a listed school makes it a typed (unlisted) school.
        if (event.isTrusted && selected && el.school.value.trim() !== selected.name) showSchool(null);
    });
    el.change.addEventListener("click", function () {
        showSchool(null);
        el.school.value = "";
        el.school.focus();
    });

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
        const shs = isNewStudent();
        el.schoolHint.hidden = !shs;
        el.school.setAttribute("aria-autocomplete", shs ? "list" : "none");
        if (!shs) schoolCombobox.close();
        // The wizard re-enables every control in a shown section; restore picker states.
        if (picker) picker.refresh();
        el.strand.disabled = !currentCurriculum();
        el.sectorField.hidden = !!selected;
        strandCombo.sync();
    }
    if (applicantType) applicantType.addEventListener("change", syncApplicantType);

    /* ── Restore (server old input first, then the browser draft) ──────── */
    const draft = readDraft();
    const restoredSchool = old.school || (draft.school_selection && draft.school_selection.name === el.school.value ? draft.school_selection : null);
    if (restoredSchool) {
        selected = restoredSchool;
        el.schoolId.value = restoredSchool.id;
        el.card.hidden = false;
        el.sectorField.hidden = true;
        el.card.querySelector('[data-school="name"]').textContent = restoredSchool.name;
        el.card.querySelector('[data-school="meta"]').textContent = describe(restoredSchool);
    }
    if (!old.shs_curriculum_id && draft.shs_curriculum_choice) {
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
})();
