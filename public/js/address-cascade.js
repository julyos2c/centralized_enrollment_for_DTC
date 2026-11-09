/* DTC Nexus — Philippine address picker for the application form.
 *
 * Region → Province / Independent City → City / Municipality → Barangay,
 * plus postal code suggestions. Data comes from the application's own
 * read-only PSGC endpoints (relative URLs, versioned with ?v=), so it works
 * the same on localhost and on the production HTTPS domain.
 *
 * Submitted fields: region_code, province_code (hidden), city_code,
 * barangay (text), postal_code. The server derives names from the codes.
 */
(function () {
    "use strict";

    const DRAFT_KEY = "dtc_application_draft";
    const NOT_LISTED = "__not_listed__";
    const REQUEST_TIMEOUT_MS = 10000;

    /* Postal code decision, kept pure for unit tests. */
    function postalSuggestion(codes, currentValue, wasAutofilled) {
        const list = Array.isArray(codes) ? codes : [];
        const value = (currentValue || "").trim();
        const known = list.some(function (c) { return c.code === value; });
        if (list.length === 1) {
            if (value === "" || wasAutofilled) return { value: list[0].code, autofilled: true, hint: "Filled in from your city or municipality. Edit it if your area uses another code." };
            return { value: value, autofilled: false, hint: known ? "" : "The usual postal code here is " + list[0].code + ". Keep yours if it is correct." };
        }
        if (list.length > 1) {
            return {
                value: wasAutofilled && !known ? "" : value,
                autofilled: false,
                hint: "This area has " + list.length + " postal codes. Pick one from the suggestions or type yours.",
            };
        }
        return { value: wasAutofilled ? "" : value, autofilled: false, hint: value ? "" : "Type your postal code if you know it." };
    }

    function isPhilippines(country) {
        const value = (country || "").trim().toLowerCase();
        return value === "" || value === "philippines" || value === "ph";
    }

    window.DtcAddressCascade = { postalSuggestion: postalSuggestion, isPhilippines: isPhilippines };

    const root = document.getElementById("addressFields");
    if (!root) return;

    let config;
    try {
        config = JSON.parse(root.dataset.lookup || "{}");
    } catch (e) {
        config = {};
    }
    let initial = {};
    try {
        initial = JSON.parse(root.dataset.old || "{}") || {};
    } catch (e) {}

    const el = {
        country: document.getElementById("country"),
        region: document.getElementById("region_code"),
        area: document.getElementById("area_select"),
        areaHint: document.getElementById("areaHint"),
        province: document.getElementById("province_code"),
        city: document.getElementById("city_code"),
        barangaySelect: document.getElementById("barangay_select"),
        barangaySelectField: document.getElementById("barangaySelectField"),
        barangay: document.getElementById("barangay_text"),
        barangayManualField: document.getElementById("barangayManualField"),
        postal: document.getElementById("postal_code"),
        postalOptions: document.getElementById("postalCodeOptions"),
        postalHint: document.getElementById("postalCodeHint"),
        status: document.getElementById("addressLookupStatus"),
        statusMessage: document.getElementById("addressLookupMessage"),
        retry: document.getElementById("addressLookupRetry"),
    };
    const phOnly = root.querySelectorAll("[data-ph-only]");

    let areas = [];
    let requestSeq = 0;
    let retryAction = null;

    /* ── Draft (shares the wizard's draft object) ─────────────────────── */
    function readDraft() {
        try {
            return JSON.parse(localStorage.getItem(DRAFT_KEY)) || {};
        } catch (e) {
            return {};
        }
    }
    function saveDraft() {
        try {
            const draft = readDraft();
            draft.region_code = el.region.value;
            draft.address_area = el.area.value;
            draft.city_code = el.city.value;
            localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
        } catch (e) {}
    }
    // Server-returned values (validation errors) win over the browser draft.
    const hasServerValues = Object.keys(initial).some(function (k) { return initial[k]; });
    if (!hasServerValues) {
        const draft = readDraft();
        initial = {
            region_code: draft.region_code || "",
            area: draft.address_area || "",
            city_code: draft.city_code || "",
            barangay: el.barangay.value || draft.barangay || "",
        };
    } else {
        initial.area = initial.province_code || initial.city_code || "";
    }

    /* ── Helpers ──────────────────────────────────────────────────────── */
    function url(template, code) {
        const path = code ? template.replace(":code", encodeURIComponent(code)) : template;
        return path + (path.indexOf("?") === -1 ? "?" : "&") + "v=" + encodeURIComponent(config.version || "1");
    }

    function fetchJson(path) {
        const controller = typeof AbortController === "function" ? new AbortController() : null;
        const timer = controller ? setTimeout(function () { controller.abort(); }, REQUEST_TIMEOUT_MS) : null;
        return fetch(path, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            signal: controller ? controller.signal : undefined,
        }).then(function (response) {
            if (timer) clearTimeout(timer);
            if (!response.ok) throw new Error("HTTP " + response.status);
            return response.json();
        }, function (error) {
            if (timer) clearTimeout(timer);
            throw error;
        });
    }

    function option(value, label, selected) {
        const o = document.createElement("option");
        o.value = value;
        o.textContent = label;
        if (selected) o.selected = true;
        return o;
    }

    function fill(select, placeholder, items, selectedValue) {
        select.innerHTML = "";
        select.appendChild(option("", placeholder));
        items.forEach(function (item) {
            select.appendChild(option(item.code, item.name, item.code === selectedValue));
        });
    }

    function reset(select, placeholder) {
        select.innerHTML = "";
        select.appendChild(option("", placeholder));
        select.disabled = true;
    }

    function showStatus(message, onRetry) {
        el.status.hidden = !message;
        el.statusMessage.textContent = message || "";
        retryAction = onRetry || null;
        el.retry.hidden = !onRetry;
    }

    function showManualBarangay(show) {
        el.barangayManualField.hidden = !show;
        el.barangaySelectField.hidden = show && el.barangaySelect.options.length <= 1;
    }

    function setPostal(codes) {
        el.postalOptions.innerHTML = "";
        codes.forEach(function (c) {
            const o = document.createElement("option");
            o.value = c.code;
            if (c.area) o.label = c.code + " — " + c.area;
            el.postalOptions.appendChild(o);
        });
        const result = postalSuggestion(codes, el.postal.value, el.postal.dataset.autofilled === "1");
        if (el.postal.value !== result.value) {
            el.postal.value = result.value;
            el.postal.dispatchEvent(new Event("input", { bubbles: true }));
        }
        el.postal.dataset.autofilled = result.autofilled ? "1" : "0";
        el.postalHint.textContent = result.hint;
        el.postalHint.hidden = !result.hint;
    }

    function failed(message, retry) {
        showStatus(message, retry);
        showManualBarangay(true);
    }

    /* ── Steps ────────────────────────────────────────────────────────── */
    function loadRegions() {
        showStatus("");
        el.region.disabled = true;
        return fetchJson(url(config.regions)).then(function (regions) {
            fill(el.region, "Select region", regions, initial.region_code);
            el.region.disabled = false;
            if (el.region.value) return loadAreas(true);
            showManualBarangay(true);
        }).catch(function () {
            reset(el.region, "Region list unavailable");
            failed("The address list could not be loaded. You can still type your barangay and postal code.", loadRegions);
        });
    }

    function loadAreas(restoring) {
        const seq = ++requestSeq;
        reset(el.area, "Loading…");
        reset(el.city, "Select a province first");
        clearBarangays();
        el.province.value = "";
        el.areaHint.hidden = true;
        const region = el.region.value;
        if (!region) {
            reset(el.area, "Select a region first");
            return Promise.resolve();
        }
        return fetchJson(url(config.areas, region)).then(function (list) {
            if (seq !== requestSeq) return;
            areas = list;
            const provinces = list.filter(function (a) { return a.type === "province"; });
            const cities = list.filter(function (a) { return a.type === "city"; });
            const wantedArea = restoring ? initial.area : "";

            if (!provinces.length) {
                // NCR (and any region without provinces): cities come straight from the region.
                reset(el.area, "Not applicable");
                el.areaHint.textContent = "This region has no provinces. Choose your city below.";
                el.areaHint.hidden = false;
                fill(el.city, "Select city / municipality", cities, restoring ? initial.city_code : "");
                el.city.disabled = false;
                if (el.city.value) return loadCityDetails(restoring);
                return;
            }

            el.area.innerHTML = "";
            el.area.appendChild(option("", "Select province or city"));
            [["Provinces", provinces], ["Cities not under a province", cities]].forEach(function (group) {
                if (!group[1].length) return;
                const og = document.createElement("optgroup");
                og.label = group[0];
                group[1].forEach(function (a) { og.appendChild(option(a.code, a.name, a.code === wantedArea)); });
                el.area.appendChild(og);
            });
            el.area.disabled = false;
            if (el.area.value) return selectArea(restoring);
        }).catch(function () {
            if (seq !== requestSeq) return;
            reset(el.area, "List unavailable");
            failed("Provinces could not be loaded.", function () { return loadAreas(restoring); });
        });
    }

    function selectArea(restoring) {
        const seq = ++requestSeq;
        clearBarangays();
        const area = areas.find(function (a) { return a.code === el.area.value; });
        if (!area) {
            el.province.value = "";
            reset(el.city, "Select a province first");
            return Promise.resolve();
        }
        if (area.type === "city") {
            // Independent / highly urbanized city: it is the city; no province.
            el.province.value = "";
            fill(el.city, "Select city / municipality", [area], area.code);
            el.city.disabled = false;
            return loadCityDetails(restoring);
        }
        el.province.value = area.code;
        reset(el.city, "Loading…");
        return fetchJson(url(config.cities, area.code)).then(function (cities) {
            if (seq !== requestSeq) return;
            fill(el.city, "Select city / municipality", cities, restoring ? initial.city_code : "");
            el.city.disabled = false;
            if (el.city.value) return loadCityDetails(restoring);
        }).catch(function () {
            if (seq !== requestSeq) return;
            reset(el.city, "List unavailable");
            failed("Cities could not be loaded.", function () { return selectArea(restoring); });
        });
    }

    function clearBarangays() {
        reset(el.barangaySelect, "Select a city first");
        el.barangaySelectField.hidden = true;
        el.barangayManualField.hidden = false;
        el.postalOptions.innerHTML = "";
        el.postalHint.hidden = true;
        // A code we filled in belongs to the previous city; one the applicant typed stays.
        if (el.postal.dataset.autofilled === "1") {
            el.postal.value = "";
            el.postal.dataset.autofilled = "0";
            el.postal.dispatchEvent(new Event("input", { bubbles: true }));
        }
    }

    function loadCityDetails(restoring) {
        const seq = ++requestSeq;
        const city = el.city.value;
        if (!city) {
            clearBarangays();
            return Promise.resolve();
        }
        reset(el.barangaySelect, "Loading barangays…");
        el.barangaySelectField.hidden = false;
        el.barangayManualField.hidden = true;
        return fetchJson(url(config.city, city)).then(function (details) {
            if (seq !== requestSeq) return;
            showStatus("");
            const barangays = details.barangays || [];
            const current = restoring ? (initial.barangay || el.barangay.value) : "";
            const match = barangays.find(function (b) { return b.name.toLowerCase() === current.trim().toLowerCase(); });

            el.barangaySelect.innerHTML = "";
            el.barangaySelect.appendChild(option("", barangays.length ? "Select barangay" : "No barangays listed"));
            barangays.forEach(function (b) { el.barangaySelect.appendChild(option(b.name, b.name, match && b.name === match.name)); });
            el.barangaySelect.appendChild(option(NOT_LISTED, "My barangay is not listed", !!current && !match));
            el.barangaySelect.disabled = false;

            if (match) {
                el.barangay.value = match.name;
                el.barangayManualField.hidden = true;
            } else if (current) {
                el.barangay.value = current;
                el.barangayManualField.hidden = false;
            } else {
                el.barangay.value = "";
                el.barangayManualField.hidden = true;
            }
            setPostal(details.postal_codes || []);
        }).catch(function () {
            if (seq !== requestSeq) return;
            reset(el.barangaySelect, "List unavailable");
            el.barangaySelectField.hidden = true;
            failed("Barangays could not be loaded. Type your barangay instead.", function () { return loadCityDetails(restoring); });
        });
    }

    /* ── Events ───────────────────────────────────────────────────────── */
    el.region.addEventListener("change", function () {
        el.barangay.value = "";
        loadAreas(false);
        saveDraft();
    });
    el.area.addEventListener("change", function () {
        el.barangay.value = "";
        selectArea(false);
        saveDraft();
    });
    el.city.addEventListener("change", function () {
        el.barangay.value = "";
        loadCityDetails(false);
        saveDraft();
    });
    el.barangaySelect.addEventListener("change", function () {
        const value = el.barangaySelect.value;
        if (value === NOT_LISTED) {
            el.barangay.value = "";
            el.barangayManualField.hidden = false;
            el.barangay.focus();
        } else {
            el.barangay.value = value;
            el.barangayManualField.hidden = true;
        }
        el.barangay.dispatchEvent(new Event("input", { bubbles: true }));
    });
    el.postal.addEventListener("input", function (event) {
        if (event.isTrusted) el.postal.dataset.autofilled = "0";
    });
    el.retry.addEventListener("click", function () {
        if (retryAction) {
            showStatus("Retrying…");
            retryAction();
        }
    });

    function updateCountry() {
        const ph = isPhilippines(el.country ? el.country.value : "");
        phOnly.forEach(function (field) { field.hidden = !ph; });
        // The barangay list is shown only once it has loaded.
        if (ph) el.barangaySelectField.hidden = el.barangaySelect.disabled;
        // Disabled controls are not submitted, so non-PH addresses send no codes.
        [el.region, el.area, el.city].forEach(function (select) {
            select.disabled = !ph || select.options.length <= 1;
        });
        el.province.disabled = !ph;
        if (!ph) {
            el.barangayManualField.hidden = false;
            showStatus("");
        }
    }
    if (el.country) el.country.addEventListener("input", updateCountry);

    if (!config.regions || typeof fetch !== "function") {
        failed("The address list is not available in this browser. You can type your barangay and postal code.");
        return;
    }
    loadRegions().then(updateCountry);
})();
