/* DTC Nexus — Philippine address picker (PSGC).
 *
 * Region → Province / Independent City → City / Municipality → Barangay,
 * with optional postal code suggestions. Data comes from the application's
 * own read-only endpoints (relative URLs, versioned with ?v=), so it works
 * the same on localhost and on the production HTTPS domain.
 *
 * Used twice on the application form: the applicant's home address
 * (#addressFields) and the previous school's location (#schoolLocationFields).
 * Controls are found inside each root by data-address roles:
 *   region, area, province (hidden), city, barangay-select, barangay-select-field,
 *   barangay (text, submitted), barangay-manual-field, area-hint,
 *   postal, postal-options, postal-hint (optional), status, status-message, retry.
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

    function readDraft() {
        try {
            return JSON.parse(localStorage.getItem(DRAFT_KEY)) || {};
        } catch (e) {
            return {};
        }
    }

    function parse(json) {
        try {
            return JSON.parse(json || "{}") || {};
        } catch (e) {
            return {};
        }
    }

    function option(value, label, selected) {
        const o = document.createElement("option");
        o.value = value;
        o.textContent = label;
        if (selected) o.selected = true;
        return o;
    }

    /**
     * @param {HTMLElement} root
     * @param {{lookup: object, initial?: object, draftKeys: {region: string, area: string, city: string, barangay: string}, country?: HTMLInputElement}} options
     */
    function createPicker(root, options) {
        const q = function (role) { return root.querySelector('[data-address="' + role + '"]'); };
        const el = {
            region: q("region"), area: q("area"), areaHint: q("area-hint"), province: q("province"), city: q("city"),
            barangaySelect: q("barangay-select"), barangaySelectField: q("barangay-select-field"),
            barangay: q("barangay"), barangayManualField: q("barangay-manual-field"),
            postal: q("postal"), postalOptions: q("postal-options"), postalHint: q("postal-hint"),
            status: q("status"), statusMessage: q("status-message"), retry: q("retry"),
        };
        const config = options.lookup || {};
        const keys = options.draftKeys;
        const phOnly = root.querySelectorAll("[data-ph-only]");
        let areas = [];
        let requestSeq = 0;
        let retryAction = null;
        let initial = options.initial || {};

        function saveDraft() {
            if (!keys) return; // staff forms keep no browser draft
            try {
                const draft = readDraft();
                draft[keys.region] = el.region.value;
                draft[keys.area] = el.area.value;
                draft[keys.city] = el.city.value;
                localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
            } catch (e) {}
        }

        // Server-returned values (validation errors) win over the browser draft.
        if (!Object.keys(initial).some(function (k) { return initial[k]; }) && keys) {
            const draft = readDraft();
            initial = {
                region_code: draft[keys.region] || "",
                area: draft[keys.area] || "",
                city_code: draft[keys.city] || "",
                barangay: el.barangay.value || draft[keys.barangay] || "",
            };
        } else {
            initial.area = initial.province_code || initial.city_code || "";
        }

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
            if (!el.status) return;
            el.status.hidden = !message;
            el.statusMessage.textContent = message || "";
            retryAction = onRetry || null;
            el.retry.hidden = !onRetry;
        }

        function failed(message, retry) {
            showStatus(message, retry);
            el.barangayManualField.hidden = false;
        }

        function setPostal(codes) {
            if (!el.postal) return;
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

        function clearBarangays() {
            reset(el.barangaySelect, "Select a city first");
            el.barangaySelectField.hidden = true;
            el.barangayManualField.hidden = false;
            if (!el.postal) return;
            el.postalOptions.innerHTML = "";
            el.postalHint.hidden = true;
            // A code we filled in belongs to the previous city; one the applicant typed stays.
            if (el.postal.dataset.autofilled === "1") {
                el.postal.value = "";
                el.postal.dataset.autofilled = "0";
                el.postal.dispatchEvent(new Event("input", { bubbles: true }));
            }
        }

        function loadRegions() {
            showStatus("");
            el.region.disabled = true;
            return fetchJson(url(config.regions)).then(function (regions) {
                fill(el.region, "Select region", regions, initial.region_code);
                el.region.disabled = false;
                if (el.region.value) return loadAreas(true);
                el.barangayManualField.hidden = false;
            }).catch(function () {
                reset(el.region, "Region list unavailable");
                failed("The address list could not be loaded. You can still type the barangay.", loadRegions);
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
                    el.areaHint.textContent = "This region has no provinces. Choose the city below.";
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
                const current = restoring ? (initial.barangay || el.barangay.value || "") : "";
                const match = barangays.find(function (b) { return b.name.toLowerCase() === current.trim().toLowerCase(); });

                el.barangaySelect.innerHTML = "";
                el.barangaySelect.appendChild(option("", barangays.length ? "Select barangay" : "No barangays listed"));
                barangays.forEach(function (b) { el.barangaySelect.appendChild(option(b.name, b.name, match && b.name === match.name)); });
                el.barangaySelect.appendChild(option(NOT_LISTED, "Barangay not listed", !!current && !match));
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
                failed("Barangays could not be loaded. Type the barangay instead.", function () { return loadCityDetails(restoring); });
            });
        }

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
        if (el.postal) {
            el.postal.addEventListener("input", function (event) {
                if (event.isTrusted) el.postal.dataset.autofilled = "0";
            });
        }
        if (el.retry) {
            el.retry.addEventListener("click", function () {
                if (retryAction) {
                    showStatus("Retrying…");
                    retryAction();
                }
            });
        }

        /** Re-apply enabled/visible states (after country or section changes). */
        function refresh() {
            const ph = options.country ? isPhilippines(options.country.value) : true;
            phOnly.forEach(function (field) { field.hidden = !ph; });
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
        if (options.country) options.country.addEventListener("input", refresh);

        /** Select a location programmatically (e.g. from a chosen school). */
        function setLocation(location) {
            initial = {
                region_code: location.region_code || "",
                area: location.province_code || location.city_code || "",
                city_code: location.city_code || "",
                barangay: location.barangay || "",
            };
            el.region.value = initial.region_code;
            if (el.region.value !== initial.region_code) return Promise.resolve();
            el.barangay.value = initial.barangay;
            return loadAreas(true).then(saveDraft);
        }

        let ready;
        if (!config.regions || typeof fetch !== "function") {
            failed("The address list is not available in this browser. You can type the barangay.");
            ready = Promise.resolve();
        } else {
            ready = loadRegions().then(refresh);
        }

        return { ready: ready, refresh: refresh, setLocation: setLocation };
    }

    window.DtcAddressCascade = { postalSuggestion: postalSuggestion, isPhilippines: isPhilippines, createPicker: createPicker };

    if (typeof document === "undefined" || !document.getElementById || !document.querySelectorAll) return;

    // Staff forms: <div data-address-picker data-lookup="…" data-old="…">.
    Array.prototype.forEach.call(document.querySelectorAll("[data-address-picker]"), function (root) {
        createPicker(root, { lookup: parse(root.dataset.lookup), initial: parse(root.dataset.old), draftKeys: null });
    });

    const home = document.getElementById("addressFields");
    if (!home) return;
    window.DtcAddressCascade.home = createPicker(home, {
        lookup: parse(home.dataset.lookup),
        initial: parse(home.dataset.old),
        country: document.getElementById("country"),
        draftKeys: { region: "region_code", area: "address_area", city: "city_code", barangay: "barangay" },
    });
})();
