/**
 * DTC EMS — Application Wizard
 *
 * Features:
 *  - Multi-step wizard navigation with per-step validation
 *  - localStorage persistence: form data survives page reload
 *  - Tab switching on Step 5 (Upload vs Requirements Checklist)
 *  - Interactive requirements checklist (checkboxes, not bullets)
 *  - Cascading PH address picker  (Region → Province → City → Barangay)
 *  - Cascading PH place of birth  (Region → Province → City, Step 2)
 *  - Postal code auto-filled from barangay's postalCode via PSGC API
 *  - Optional free-text religion field
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ════════════════════════════════════════════════════════════
       CONFIG
    ════════════════════════════════════════════════════════════ */

    const TOTAL       = 6;
    const STORAGE_KEY = 'dtc_application_draft';
    const CHECKS_KEY  = 'dtc_req_checks';
    let   current     = 1;


    /* ════════════════════════════════════════════════════════════
       ELEMENTS — WIZARD
    ════════════════════════════════════════════════════════════ */

    const panels     = document.querySelectorAll('.wiz-panel');
    const steps      = document.querySelectorAll('.wiz-step');
    const connectors = document.querySelectorAll('.wiz-connector');
    const bar        = document.getElementById('wizardBar');
    const labelCur   = document.getElementById('wizCurrentStep');
    const btnPrev    = document.getElementById('wizPrev');
    const btnNext    = document.getElementById('wizNext');
    const btnSubmit  = document.getElementById('wizSubmit');
    const tabBtns    = document.querySelectorAll('.doc-tab-btn');
    const tabPanels  = document.querySelectorAll('.doc-tab-panel');

    /* Step 1 / 5 — applicant reactive */
    const applicantType            = document.getElementById('academic_status');
    const maritalStatus            = document.getElementById('marital_status');
    const disability               = document.getElementById('disability');
    const documentSections         = document.querySelectorAll('.document-section');
    const educationSections        = document.querySelectorAll('.education-section');
    const noDocumentNotice         = document.getElementById('noDocumentNotice');
    const marriageDocument         = document.getElementById('marriageDocument');
    const spouseField              = document.getElementById('spouseField');
    const pwdIdField               = document.getElementById('pwdIdField');
    const crossEnrolleeNotice      = document.getElementById('crossEnrolleeNotice');
    const physicalRequirementsCard = document.getElementById('physicalRequirementsCard');
    const physicalRequirementsList = document.getElementById('physicalRequirementsList');

    /* Step 3 — current address cascade */
    /* Step 2 — place of birth cascade */


    /* ════════════════════════════════════════════════════════════
       PHYSICAL REQUIREMENTS DATA
    ════════════════════════════════════════════════════════════ */

    const physicalRequirements = {
        new_student: [
            'Form 138 or equivalent — original/required physical copy',
            'Certificate of Good Moral Character — original/required physical copy',
            'PSA Birth Certificate — required physical copy',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope',
        ],
        transferee: [
            'Certificate of Transfer Credentials',
            'Transcript of Records',
            'Certificate of Good Moral Character',
            'PSA Birth Certificate',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope',
        ],
        shiftee: [], returnee: [], cross_enrollee: [],
    };


    /* ════════════════════════════════════════════════════════════
       WIZARD NAVIGATION
    ════════════════════════════════════════════════════════════ */

    function goTo(n, skipValidation) {
        if (!skipValidation && n > current && !validateStep(current)) return;
        current = Math.max(1, Math.min(TOTAL, n));

        panels.forEach(function (p, i) { p.classList.toggle('active', i + 1 === current); });
        steps.forEach(function (s, i) {
            s.classList.remove('active', 'completed');
            if (i + 1 === current) s.classList.add('active');
            if (i + 1 <  current) s.classList.add('completed');
            if (i + 1 === current) s.setAttribute('aria-current', 'step');
            else s.removeAttribute('aria-current');
        });
        connectors.forEach(function (c, i) { c.classList.toggle('completed', i + 1 < current); });

        bar.style.width = ((current / TOTAL) * 100) + '%';
        labelCur.textContent = current;
        btnPrev.style.visibility = current === 1 ? 'hidden' : 'visible';

        if (current === TOTAL) {
            btnNext.classList.add('d-none');
            btnSubmit.classList.remove('d-none');
        } else {
            btnNext.classList.remove('d-none');
            btnSubmit.classList.add('d-none');
        }
        if (current === 6) buildReview();

        /* Swap completed step icons to a plain check before Lucide re-renders.
           Lucide replaces <i data-lucide="..."> with <svg> each time createIcons()
           runs, so we must reset the icon attribute first. */
        steps.forEach(function (s) {
            var dot = s.querySelector('.wiz-step-dot');
            if (!dot) return;
            var existing = dot.querySelector('svg') || dot.querySelector('i');
            if (s.classList.contains('completed')) {
                /* Replace with check icon */
                if (existing) existing.remove();
                var checkEl = document.createElement('i');
                checkEl.setAttribute('data-lucide', 'check');
                dot.appendChild(checkEl);
            } else {
                /* Restore original icon (stored on the step element) */
                var origIcon = s.getAttribute('data-original-icon');
                if (origIcon && existing) {
                    existing.setAttribute('data-lucide', origIcon);
                } else if (origIcon && !existing) {
                    var origEl = document.createElement('i');
                    origEl.setAttribute('data-lucide', origIcon);
                    dot.appendChild(origEl);
                }
            }
        });

        if (window.lucide) lucide.createIcons();
        const heading = document.querySelector('#step-' + current + ' .wiz-panel-title');
        if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({ preventScroll: true }); }
        const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    }


    /* ════════════════════════════════════════════════════════════
       VALIDATION
    ════════════════════════════════════════════════════════════ */

    function validateStep(n) {
        const panel = document.getElementById('step-' + n);
        if (!panel) return true;
        let ok = true;
        panel.querySelectorAll('[required]').forEach(function (el) {
            if (el.type === 'hidden') return;
            const field = el.closest('.wiz-field');
            if (!el.value.trim()) {
                el.classList.add('wiz-invalid');
                if (field) {
                    let err = field.querySelector('.wiz-err-msg');
                    if (!err) {
                        err = document.createElement('span');
                        err.className = 'wiz-err-msg wiz-err-dynamic';
                        err.innerHTML = '<i data-lucide="alert-circle"></i> This field is required.';
                        field.appendChild(err);
                        if (window.lucide) lucide.createIcons({ nodes: [err] });
                    }
                    field.classList.remove('wiz-shake');
                    void field.offsetWidth;
                    field.classList.add('wiz-shake');
                    field.addEventListener('animationend', function () {
                        field.classList.remove('wiz-shake');
                    }, { once: true });
                }
                ok = false;
            } else {
                el.classList.remove('wiz-invalid');
                if (field) { const d = field.querySelector('.wiz-err-dynamic'); if (d) d.remove(); }
            }
        });
        return ok;
    }

    document.querySelectorAll('.wiz-input').forEach(function (el) {
        function clear() {
            el.classList.remove('wiz-invalid');
            const field = el.closest('.wiz-field');
            const d = field && field.querySelector('.wiz-err-dynamic');
            if (d) d.remove();
        }
        el.addEventListener('input',  clear);
        el.addEventListener('change', clear);
    });

    btnNext.addEventListener('click', function () { goTo(current + 1); });
    btnPrev.addEventListener('click', function () { goTo(current - 1, true); });

    steps.forEach(function (s, i) {
        /* Store the original icon name so goTo() can restore it when going back */
        var origI = s.querySelector('.wiz-step-dot i');
        if (origI) s.setAttribute('data-original-icon', origI.getAttribute('data-lucide'));

        s.addEventListener('click', function () { if (s.classList.contains('completed')) goTo(i + 1, true); });
        s.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && s.classList.contains('completed')) { e.preventDefault(); goTo(i + 1, true); }
        });
    });

    /* ── Submit guard: single combined consent checkbox ── */
    let submissionInProgress = false;
    document.getElementById('applicationForm').addEventListener('submit', function (e) {
        const chk    = document.getElementById('ack_enrollment');
        const ackErr = document.getElementById('ackError');
        const hidAck = document.getElementById('acknowledgement');

        if (!chk) return;

        if (!chk.checked) {
            e.preventDefault();
            ackErr.classList.remove('d-none');
            ackErr.scrollIntoView({ behavior: 'smooth', block: 'center' });

            /* Shake the label */
            const lbl = chk.closest('.simple-ack-label') || chk.nextElementSibling;
            if (lbl) {
                lbl.classList.add('consent-check-shake');
                lbl.addEventListener('animationend', function () {
                    lbl.classList.remove('consent-check-shake');
                }, { once: true });
            }
            chk.addEventListener('change', function () {
                if (chk.checked) { hidAck.value = '1'; ackErr.classList.add('d-none'); }
            }, { once: true });
        } else {
            if (submissionInProgress) { e.preventDefault(); return; }
            submissionInProgress = true;
            btnSubmit.disabled = true;
            btnSubmit.setAttribute('aria-busy', 'true');
            hidAck.value = '1';
            try { localStorage.removeItem(STORAGE_KEY); } catch (ex) {}
        }
    });

    /* ── Submit confirmation modal ── */
    const confirmModal = document.getElementById('confirmModal');
    const confirmSubmit = document.getElementById('confirmSubmit');
    const confirmCancel = document.getElementById('confirmCancel');

    if (btnSubmit && confirmModal) {
        btnSubmit.addEventListener('click', function (e) {
            e.preventDefault();
            const chk = document.getElementById('ack_enrollment');
            const ackErr = document.getElementById('ackError');
            const hidAck = document.getElementById('acknowledgement');

            if (!chk || !chk.checked) {
                e.preventDefault();
                if (ackErr) {
                    ackErr.classList.remove('d-none');
                    ackErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                const lbl = chk.closest('.simple-ack-label') || chk.nextElementSibling;
                if (lbl) {
                    lbl.classList.add('consent-check-shake');
                    lbl.addEventListener('animationend', function () {
                        lbl.classList.remove('consent-check-shake');
                    }, { once: true });
                }
                chk.addEventListener('change', function () {
                    if (chk.checked) { hidAck.value = '1'; if (ackErr) ackErr.classList.add('d-none'); }
                }, { once: true });
                return;
            }

            confirmModal.classList.add('active');
        });

        if (confirmSubmit) {
            confirmSubmit.addEventListener('click', function () {
                const form = document.getElementById('applicationForm');
                if (form) {
                    if (confirmSubmit.disabled) return;
                    confirmSubmit.disabled = true;
                    confirmSubmit.textContent = 'Submitting…';
                    btnSubmit.disabled = true;
                    btnSubmit.setAttribute('aria-busy', 'true');
                    try { localStorage.removeItem(STORAGE_KEY); } catch (ex) {}
                    form.submit();
                }
            });
        }

        if (confirmCancel) {
            confirmCancel.addEventListener('click', function () {
                confirmModal.classList.remove('active');
            });
        }

        // Close modal when clicking outside
        confirmModal.addEventListener('click', function (e) {
            if (e.target === confirmModal) {
                confirmModal.classList.remove('active');
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && confirmModal.classList.contains('active')) {
                confirmModal.classList.remove('active');
            }
        });
    }

    /* ── Privacy policy modal ── */
    const privacyModalEl = document.getElementById('privacyModal');
    const closePrivacyModalBtn = document.getElementById('closePrivacyModal');
    const openPrivacyModalLink = document.getElementById('openPrivacyModal');

    if (privacyModalEl) {
        // Open modal when clicking Privacy Policy link
        if (openPrivacyModalLink) {
            openPrivacyModalLink.addEventListener('click', function (e) {
                e.preventDefault();
                privacyModalEl.classList.add('active');
            });
        }

        // Close modal when clicking "I Understand"
        if (closePrivacyModalBtn) {
            closePrivacyModalBtn.addEventListener('click', function () {
                privacyModalEl.classList.remove('active');
            });
        }

        // Close modal when clicking outside
        privacyModalEl.addEventListener('click', function (e) {
            if (e.target === privacyModalEl) {
                privacyModalEl.classList.remove('active');
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && privacyModalEl.classList.contains('active')) {
                privacyModalEl.classList.remove('active');
            }
        });
    }

    /* ── Terms modal ── */
    const termsModalEl = document.getElementById('termsModal');
    const closeTermsModalBtn = document.getElementById('closeTermsModal');
    const openTermsModalLink = document.getElementById('openTermsModal');

    if (termsModalEl) {
        // Open modal when clicking Terms link
        if (openTermsModalLink) {
            openTermsModalLink.addEventListener('click', function (e) {
                e.preventDefault();
                termsModalEl.classList.add('active');
            });
        }

        // Close modal when clicking "I Understand"
        if (closeTermsModalBtn) {
            closeTermsModalBtn.addEventListener('click', function () {
                termsModalEl.classList.remove('active');
            });
        }

        // Close modal when clicking outside
        termsModalEl.addEventListener('click', function (e) {
            if (e.target === termsModalEl) {
                termsModalEl.classList.remove('active');
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && termsModalEl.classList.contains('active')) {
                termsModalEl.classList.remove('active');
            }
        });
    }


    /* ════════════════════════════════════════════════════════════
       FILE PICKER LABELS
    ════════════════════════════════════════════════════════════ */

    document.querySelectorAll('.document-input-file').forEach(function (input) {
        const wrap = input.closest('.document-input-control');
        const fn   = wrap && wrap.querySelector('.document-input-filename');
        input.addEventListener('change', function () {
            if (!fn) return;
            if (input.files && input.files.length) {
                fn.textContent = input.files[0].name;
                wrap.classList.add('has-file');
            } else {
                fn.textContent = fn.dataset.placeholder || 'No file chosen';
                wrap.classList.remove('has-file');
            }
        });
    });


    /* ════════════════════════════════════════════════════════════
       DOCUMENT TABS
    ════════════════════════════════════════════════════════════ */

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const t = btn.getAttribute('data-tab');
            tabBtns.forEach(function (b)  { b.classList.toggle('active', b === btn); });
            tabPanels.forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-tab-panel') === t); });
            if (window.lucide) lucide.createIcons();
        });
    });


    /* ════════════════════════════════════════════════════════════
       REQUIREMENTS CHECKLIST
    ════════════════════════════════════════════════════════════ */

    function loadChecks() { try { return JSON.parse(localStorage.getItem(CHECKS_KEY)) || {}; } catch (e) { return {}; } }
    function saveCheck(type, i, v) {
        const all = loadChecks();
        if (!all[type]) all[type] = {};
        all[type][i] = v;
        try { localStorage.setItem(CHECKS_KEY, JSON.stringify(all)); } catch (e) {}
    }
    function buildChecklist(type) {
        const reqs  = physicalRequirements[type] || [];
        const saved = (loadChecks()[type]) || {};
        physicalRequirementsList.innerHTML = '';
        if (!reqs.length) { physicalRequirementsCard.classList.add('d-none'); return; }
        physicalRequirementsCard.classList.remove('d-none');
        reqs.forEach(function (text, i) {
            const id  = 'req_' + type + '_' + i;
            const li  = document.createElement('li');
            const chk = document.createElement('input');
            chk.type = 'checkbox'; chk.className = 'wiz-req-checkbox'; chk.id = id; chk.checked = saved[i] === true;
            const lbl = document.createElement('label');
            lbl.className = 'wiz-req-label'; lbl.htmlFor = id;
            lbl.innerHTML = '<span class="wiz-req-box"></span><span class="wiz-req-text">' + text + '</span>';
            chk.addEventListener('change', function () { saveCheck(type, i, chk.checked); });
            li.appendChild(chk); li.appendChild(lbl);
            physicalRequirementsList.appendChild(li);
        });
    }


    /* ════════════════════════════════════════════════════════════
       APPLICANT-TYPE REACTIVE SECTIONS
    ════════════════════════════════════════════════════════════ */

    function updateApplicantType() {
        const t = applicantType.value;
        documentSections.forEach(function (s) {
            const visible = s.getAttribute('data-type') === t;
            s.classList.toggle('d-none', !visible);
            s.querySelectorAll('input[type="file"]').forEach(function (field) { field.disabled = !visible; });
        });
        noDocumentNotice.classList.toggle('d-none', !!t);
        crossEnrolleeNotice.classList.toggle('d-none', t !== 'cross_enrollee');
        educationSections.forEach(function (section) {
            const types = (section.getAttribute('data-education-for') || '').split(',').map(function (value) { return value.trim(); });
            const visible = types.indexOf(t) !== -1;
            section.classList.toggle('d-none', !visible);
            section.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !visible;
            });
        });
        buildChecklist(t);
    }
    function updateMaritalStatus() {
        const m = maritalStatus.value === 'married';
        spouseField.classList.toggle('d-none', !m);
        marriageDocument.classList.toggle('d-none', !m);
        marriageDocument.querySelectorAll('input[type="file"]').forEach(function (field) { field.disabled = !m; });
        if (!m) document.getElementById('spouse_name').value = '';
    }
    function updateDisability() {
        pwdIdField.classList.toggle('d-none', disability.value.trim() === '');
    }

    applicantType.addEventListener('change', function () { updateApplicantType(); saveField(applicantType); });
    maritalStatus.addEventListener('change', function () { updateMaritalStatus(); saveField(maritalStatus); suggestHonorific(); });
    disability.addEventListener('input',    function () { updateDisability();    saveField(disability); });

    /* Auto-suggest honorific based on gender + marital status */
    function suggestHonorific() {
        const honorifSel = document.getElementById('honorific');
        if (!honorifSel || honorifSel.value) return; /* don't override user choice */
        const g = document.getElementById('gender')        ? document.getElementById('gender').value        : '';
        const m = document.getElementById('marital_status')? document.getElementById('marital_status').value: '';
        if (g === 'male')   { honorifSel.value = 'Mr.'; }
        else if (g === 'female') {
            honorifSel.value = (m === 'married') ? 'Mrs.' : 'Ms.';
        }
        if (honorifSel.value) saveField(honorifSel);
    }
    document.getElementById('gender')        && document.getElementById('gender').addEventListener('change',        function () { suggestHonorific(); saveField(document.getElementById('gender')); });
    document.getElementById('marital_status')&& document.getElementById('marital_status').addEventListener('change', suggestHonorific);


    /* ════════════════════════════════════════════════════════════
       PSGC API  — shared cache + fetch helper
    ════════════════════════════════════════════════════════════ */

    /* Local PSGC location picker. The API and submit validator share one snapshot. */
    const locationModal = document.getElementById('locPickerModal');
    const locationSearch = document.getElementById('locPickerSearch');
    const locationList = document.getElementById('locPickerList');
    const locationTitle = document.getElementById('locPickerTitle');
    const locationBack = document.getElementById('locPickerBack');
    const locationClose = document.getElementById('locPickerClose');
    const regionCodeInput = document.getElementById('region_code');
    const provinceCodeInput = document.getElementById('province_code');
    const cityCodeInput = document.getElementById('city_code');
    const locationCache = new Map();
    const locationRequests = new Map();
    let locationLevel = 'region';
    let locationItems = [];
    let locationOpener = null;
    let locationRequest = 0;
    let userChangedLocation = false;
    let savedBodyOverflow = '';

    const locationUrls = locationModal ? {
        regions: locationModal.dataset.regionsUrl,
        areas: locationModal.dataset.areasUrl,
        cities: locationModal.dataset.citiesUrl
    } : null;

    function locationText(id) { return document.getElementById(id); }
    function rememberLocation(el) { if (el) saveField(el); }
    function normalizeSearch(value) { return value.toLocaleLowerCase().trim().replace(/\s+/g, ' '); }
    function htmlEscape(value) {
        return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function locationUrl(template, code) { return template.replace('__CODE__', encodeURIComponent(code)); }

    function fetchLocations(url) {
        if (locationCache.has(url)) return Promise.resolve(locationCache.get(url));
        if (locationRequests.has(url)) return locationRequests.get(url);
        const request = fetch(url, { headers: { Accept: 'application/json' } }).then(function (response) {
            if (!response.ok) throw new Error('Location data is unavailable. Please try again.');
            return response.json();
        }).then(function (items) {
            locationCache.set(url, items);
            locationRequests.delete(url);
            return items;
        }).catch(function (error) {
            locationRequests.delete(url);
            throw error;
        });
        locationRequests.set(url, request);
        return request;
    }

    function setLocationCode(input, labelInput, option) {
        input.value = option ? option.code : '';
        labelInput.value = option ? option.name : '';
        rememberLocation(input);
        rememberLocation(labelInput);
    }

    function updateLocationButtons() {
        [
            ['locRegionTrigger', 'region_text', 'Select region'],
            ['locProvinceTrigger', 'province_text', 'Select province or independent city'],
            ['locCityTrigger', 'city_text', 'Select city / municipality']
        ].forEach(function (entry) {
            const button = locationText(entry[0]);
            const label = locationText(entry[1]);
            if (button) {
                const display = button.querySelector('[data-location-value]');
                display.textContent = entry[0] === 'locProvinceTrigger' && !provinceCodeInput.value && cityCodeInput.value
                    ? 'Independent city selected'
                    : (label.value || entry[2]);
                button.classList.toggle('has-value', !!label.value);
            }
        });
    }

    function openLocationPicker(level, opener) {
        if (!locationModal) return;
        locationLevel = level;
        locationOpener = opener || document.activeElement;
        savedBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        locationModal.hidden = false;
        locationModal.setAttribute('aria-hidden', 'false');
        locationSearch.value = '';
        showLocationItems();
        window.requestAnimationFrame(function () { locationSearch.focus(); });
    }

    function closeLocationPicker() {
        if (!locationModal || locationModal.hidden) return;
        locationModal.hidden = true;
        locationModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = savedBodyOverflow;
        updateLocationButtons();
        if (locationOpener) locationOpener.focus();
    }

    function showLocationItems() {
        const thisRequest = ++locationRequest;
        const regionCode = regionCodeInput.value;
        const provinceCode = provinceCodeInput.value;
        const titles = { region: 'Select region', area: 'Select province or independent city', city: 'Select city / municipality' };
        locationTitle.textContent = titles[locationLevel];
        locationBack.hidden = locationLevel === 'region';
        locationList.innerHTML = '<div class="loc-picker-loading"><span class="loc-picker-spinner" aria-hidden="true"></span>Loading locations…</div>';

        let request;
        if (locationLevel === 'region') request = fetchLocations(locationUrls.regions);
        else if (locationLevel === 'area') request = fetchLocations(locationUrl(locationUrls.areas, regionCode));
        else request = fetchLocations(locationUrl(locationUrls.cities, provinceCode));

        request.then(function (items) {
            if (thisRequest !== locationRequest || locationModal.hidden) return;
            locationItems = items;
            renderLocationItems();
        }).catch(function () {
            if (thisRequest !== locationRequest) return;
            locationList.innerHTML = '<div class="loc-picker-empty">Location data could not be loaded. <button type="button" class="loc-picker-retry">Try again</button></div>';
            const retry = locationList.querySelector('.loc-picker-retry');
            retry.addEventListener('click', showLocationItems);
        });
    }

    function renderLocationItems() {
        const query = normalizeSearch(locationSearch.value);
        const filtered = locationItems.filter(function (item) {
            return normalizeSearch(item.name).includes(query);
        });
        if (!filtered.length) {
            locationList.innerHTML = '<div class="loc-picker-empty" role="status">No locations match “' + htmlEscape(locationSearch.value) + '”.</div>';
            return;
        }

        const selectedCode = locationLevel === 'region' ? regionCodeInput.value
            : locationLevel === 'area' ? (provinceCodeInput.value || cityCodeInput.value) : cityCodeInput.value;
        locationList.innerHTML = filtered.map(function (item) {
            const selected = selectedCode === item.code;
            const itemType = locationLevel === 'area' && item.type === 'city' ? ' · Independent city' : '';
            return '<button type="button" class="loc-picker-item' + (selected ? ' selected' : '') + '" data-code="' + htmlEscape(item.code) + '" data-name="' + htmlEscape(item.name) + '" data-type="' + htmlEscape(item.type || '') + '" aria-pressed="' + (selected ? 'true' : 'false') + '">' + htmlEscape(item.name + itemType) + (selected ? '<span aria-hidden="true">✓</span>' : '') + '</button>';
        }).join('');
    }

    function chooseLocation(item) {
        userChangedLocation = true;
        if (locationLevel === 'region') {
            setLocationCode(regionCodeInput, locationText('region_text'), item);
            setLocationCode(provinceCodeInput, locationText('province_text'), null);
            setLocationCode(cityCodeInput, locationText('city_text'), null);
            locationLevel = 'area';
            locationSearch.value = '';
            showLocationItems();
        } else if (locationLevel === 'area' && item.type === 'province') {
            setLocationCode(provinceCodeInput, locationText('province_text'), item);
            setLocationCode(cityCodeInput, locationText('city_text'), null);
            locationLevel = 'city';
            locationSearch.value = '';
            showLocationItems();
        } else if (locationLevel === 'area') {
            setLocationCode(provinceCodeInput, locationText('province_text'), null);
            setLocationCode(cityCodeInput, locationText('city_text'), item);
            closeLocationPicker();
        } else {
            setLocationCode(cityCodeInput, locationText('city_text'), item);
            closeLocationPicker();
        }
        updateLocationButtons();
    }

    if (locationModal) {
        const locationDraft = loadDraft();
        [regionCodeInput, provinceCodeInput, cityCodeInput].forEach(function (input) {
            if (input && !input.value && locationDraft[input.name]) input.value = locationDraft[input.name];
        });
        [['region_text', 'region'], ['province_text', 'province'], ['city_text', 'city']].forEach(function (pair) {
            const input = locationText(pair[0]);
            if (input && !input.value && locationDraft[pair[1]]) input.value = locationDraft[pair[1]];
        });
        const countryInput = document.getElementById('country');
        const internationalField = document.getElementById('internationalLocationField');
        function updateInternationalAddress() {
            if (internationalField) internationalField.hidden = !countryInput || normalizeSearch(countryInput.value) === 'philippines';
        }
        if (countryInput) countryInput.addEventListener('input', updateInternationalAddress);
        updateInternationalAddress();
        document.querySelectorAll('[data-location-level]').forEach(function (button) {
            button.addEventListener('click', function () {
                const level = button.dataset.locationLevel;
                const nextLevel = level === 'province'
                    ? (regionCodeInput.value ? 'area' : 'region')
                    : level === 'city'
                        ? (provinceCodeInput.value ? 'city' : regionCodeInput.value ? 'area' : 'region')
                        : 'region';
                openLocationPicker(nextLevel, button);
            });
        });
        locationClose.addEventListener('click', closeLocationPicker);
        locationModal.querySelector('.loc-picker-backdrop').addEventListener('click', closeLocationPicker);
        locationBack.addEventListener('click', function () {
            locationLevel = locationLevel === 'city' ? 'area' : 'region';
            locationSearch.value = '';
            showLocationItems();
        });
        locationSearch.addEventListener('input', renderLocationItems);
        locationList.addEventListener('click', function (event) {
            const button = event.target.closest('.loc-picker-item');
            if (!button) return;
            chooseLocation({ code: button.dataset.code, name: button.dataset.name, type: button.dataset.type });
        });
        locationList.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            const buttons = Array.from(locationList.querySelectorAll('.loc-picker-item'));
            const index = buttons.indexOf(document.activeElement);
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            const next = buttons[(index + direction + buttons.length) % buttons.length];
            if (next) { event.preventDefault(); next.focus(); }
        });
        locationSearch.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                const first = locationList.querySelector('.loc-picker-item');
                if (first) { event.preventDefault(); first.focus(); }
            }
        });
        document.addEventListener('keydown', function (event) {
            if (locationModal.hidden) return;
            if (event.key === 'Escape') { event.preventDefault(); closeLocationPicker(); }
            if (event.key === 'Tab') {
                const focusable = Array.from(locationModal.querySelectorAll('button:not([disabled]), input:not([disabled])')).filter(el => !el.hidden);
                const first = focusable[0], last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });

        // Rehydrate saved/old input in parent-to-child order after validation or refresh.
        fetchLocations(locationUrls.regions).then(function () {
            if (userChangedLocation) return;
            const regionName = locationText('region_text').value;
            if (!regionCodeInput.value && regionName) {
                const regions = locationCache.get(locationUrls.regions) || [];
                const match = regions.find(item => item.name === regionName);
                if (match) regionCodeInput.value = match.code;
            }
            if (!regionCodeInput.value) return;
            return fetchLocations(locationUrl(locationUrls.areas, regionCodeInput.value)).then(function (areas) {
                if (userChangedLocation) return;
                const area = areas.find(item => item.code === (provinceCodeInput.value || cityCodeInput.value));
                if (area && area.type === 'province') {
                    if (!provinceCodeInput.value) provinceCodeInput.value = area.code;
                    return fetchLocations(locationUrl(locationUrls.cities, provinceCodeInput.value)).then(function (cities) {
                        if (userChangedLocation) return;
                        const selected = cities.find(item => item.code === cityCodeInput.value) || cities.find(item => item.name === locationText('city_text').value);
                        if (selected) { cityCodeInput.value = selected.code; locationText('city_text').value = selected.name; }
                    });
                }
                const direct = areas.find(item => item.type === 'city' && (item.code === cityCodeInput.value || item.name === locationText('city_text').value));
                if (direct) { provinceCodeInput.value = ''; locationText('province_text').value = ''; cityCodeInput.value = direct.code; locationText('city_text').value = direct.name; }
            }).then(updateLocationButtons);
        }).catch(function () { updateLocationButtons(); });
    }


    /* Location select names are persisted manually above; exclude from generic loop */
    const LOC_NAMES = ['region','province','city','region_code','province_code','city_code'];

    const PERSIST_SELECTOR = [
        'input[type="text"]',
        'input[type="email"]',
        'input[type="date"]',
        'input[type="tel"]',
        'select',
        'textarea',
    ].join(', ');

    function loadDraft() { try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; } catch (e) { return {}; } }
    function saveDraft(d) { try { localStorage.setItem(STORAGE_KEY, JSON.stringify(d)); } catch (e) {} }
    function saveField(el) {
        if (!el || !el.name) return;
        const d = loadDraft(); d[el.name] = el.value; saveDraft(d);
    }

    function restoreDraft() {
        const draft = loadDraft();
        if (!Object.keys(draft).length) return;
        document.querySelectorAll(PERSIST_SELECTOR).forEach(function (el) {
            if (LOC_NAMES.indexOf(el.name) !== -1) return;   /* handled by cascade */
            if (!el.name || el.value !== '') return;
            if (draft[el.name] !== undefined) el.value = draft[el.name];
        });
    }

    document.querySelectorAll(PERSIST_SELECTOR).forEach(function (el) {
        if (LOC_NAMES.indexOf(el.name) !== -1) return;
        el.addEventListener('change', function () { saveField(el); });
        if (el.tagName === 'INPUT' && el.type !== 'date') {
            el.addEventListener('input', function () { saveField(el); });
        }
    });

    restoreDraft();
    const NAME_FIELDS = ['first_name', 'middle_name', 'last_name', 'father_name', 'mother_name', 'spouse_name'];
    NAME_FIELDS.forEach(function (name) {
        const input = document.querySelector('[name="' + name + '"]');
        if (!input) return;
        input.addEventListener('blur', function () {
            input.value = normalizeName(input.value);
            saveField(input);
        });
    });
    updateApplicantType();
    updateMaritalStatus();
    updateDisability();

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
        const form = document.getElementById('applicationForm');
        if (!form) return;

        /* Helper: get display value for a field by name */
        function val(name) {
            const el = form.querySelector('[name="' + name + '"]:not([type="hidden"])') ||
                       form.querySelector('[name="' + name + '"]');
            if (!el) return '—';
            if (el.tagName === 'SELECT') {
                const opt = el.options[el.selectedIndex];
                return (opt && opt.text && opt.value) ? opt.text : '—';
            }
            return el.value.trim() || '—';
        }

        function nameVal(name) {
            const value = val(name);
            return value === '—' ? value : normalizeName(value);
        }

        /* Helper: get program name from select */
        function programName() {
            const sel = document.getElementById('program_id');
            if (!sel) return '—';
            const opt = sel.options[sel.selectedIndex];
            return (opt && opt.value) ? opt.text : '—';
        }

        /* Helper: assemble full address from hidden fields + plain fields */
        function fullAddress() {
            const street   = val('current_address');
            const barangay = document.getElementById('barangay_text') ? document.getElementById('barangay_text').value.trim() : '';
            const city     = document.getElementById('city_text')     ? document.getElementById('city_text').value.trim()     : '';
            const province = document.getElementById('province_text') ? document.getElementById('province_text').value.trim() : '';
            const region   = document.getElementById('region_text')   ? document.getElementById('region_text').value.trim()   : '';
            const postal   = val('postal_code');
            const country  = val('country');

            const parts = [street, barangay ? 'Brgy. ' + barangay : '', city, province, region].filter(function(p){ return p && p !== '—'; });
            let addr = parts.join(', ');
            if (postal && postal !== '—') addr += ' ' + postal;
            if (country && country !== '—') addr += ', ' + country;
            return addr || '—';
        }

        /* Section builder */
        function section(title, icon, rows, step) {
            const filled = rows.filter(function(r){ return r[1] && r[1] !== '—'; });
            const html = rows.map(function(r) {
                const empty = !r[1] || r[1] === '—';
                return '<div class="rev-row' + (empty ? ' rev-row--empty' : '') + '">' +
                    '<span class="rev-label">' + r[0] + '</span>' +
                    '<span class="rev-value">' + (empty ? '<em>Not provided</em>' : escHtml(r[1])) + '</span>' +
                '</div>';
            }).join('');
            return '<div class="rev-section">' +
                '<div class="rev-section-head">' +
                    '<i data-lucide="' + icon + '"></i>' +
                    '<span>' + title + '</span>' +
                    '<span class="rev-badge">' + filled.length + ' / ' + rows.length + '</span>' +
                    '<button type="button" class="rev-edit" data-edit-step="' + step + '" aria-label="Edit ' + escHtml(title) + '">Edit</button>' +
                '</div>' +
                '<div class="rev-section-body">' + html + '</div>' +
            '</div>';
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        /* Applicant type label map */
        const typeLabels = {
            new_student:'New Student / First Year', transferee:'Transferee',
            shiftee:'Shiftee', returnee:'Returnee / Readmitted', cross_enrollee:'Cross-Enrollee'
        };
        const rawType = val('academic_status');
        const typeLabel = typeLabels[rawType] || rawType;

        /* Assemble display full name */
        function fullName() {
            const honorific = val('honorific') !== '—' ? val('honorific') : '';
            const first  = nameVal('first_name')  !== '—' ? nameVal('first_name')  : '';
            const middle = nameVal('middle_name') !== '—' ? nameVal('middle_name') : '';
            const last   = nameVal('last_name')   !== '—' ? nameVal('last_name')   : '';
            const suffix = val('suffix')      !== '—' ? val('suffix')      : '';
            const mi = middle ? middle.charAt(0).toUpperCase() + '.' : '';
            const name = [first, mi, last].filter(Boolean).join(' ');
            return [honorific, name, suffix].filter(Boolean).join(' ') || '—';
        }

        const educationRows = [
            ['Education level', val('education_level')],
            ['Completion status', val('education_status')],
            ['Most recent school', val('most_recent_school')],
            ['School year completed or last attended', val('school_year_completed')],
            ['School location', val('school_location')],
        ];
        if (rawType === 'new_student') educationRows.push(['Senior High School strand', val('shs_strand')]);
        if (['transferee', 'shiftee', 'returnee'].indexOf(rawType) !== -1) {
            educationRows.push(['Previous or current program', val('previous_program')]);
            educationRows.push(['Last year level or semester attended', val('last_year_level')]);
        }
        if (rawType === 'cross_enrollee') {
            educationRows.push(['Home institution', val('home_institution')]);
            educationRows.push(['Current course or program', val('home_program')]);
            educationRows.push(['Last year level or semester attended', val('last_year_level')]);
        }

        const documentRows = [];
        form.querySelectorAll('input[type="file"]').forEach(function (input) {
            if (input.disabled || !input.files || !input.files.length) return;
            const label = input.closest('.document-input')?.querySelector('label[for="' + input.id + '"]');
            documentRows.push([label ? label.textContent.trim() : 'Supporting document', input.files[0].name]);
        });
        if (!documentRows.length) documentRows.push(['Supporting documents', 'No files selected']);

        const html =
            section('Admission Details', 'graduation-cap', [
                ['Applicant Type',   typeLabel],
                ['Intended Program', programName()],
            ], 1) +
            section('Personal Information', 'user', [
                ['Full Name',      fullName()],
                ['Gender',         val('gender')],
                ['Date of Birth',  val('birthdate')],
                ['Age',            (function() {
                    const dob = val('birthdate');
                    if (!dob || dob === '—') return '—';
                    const today = new Date();
                    const birth = new Date(dob);
                    let age = today.getFullYear() - birth.getFullYear();
                    const m = today.getMonth() - birth.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                    return age > 0 ? age + ' years old' : '—';
                })()],
                ['Place of Birth', val('birth_place')],
                ['Religion',       val('religion')],
                ['Nationality',    val('nationality')],
                ['LRN',            val('lrn')],
                ['Marital Status', val('marital_status')],
                ['Spouse Name',    nameVal('spouse_name')],
            ], 2) +
            section('Contact & Address', 'map-pin', [
                ['Email',    val('email')],
                ['Phone',    val('phone')],
                ['Address',  fullAddress()],
            ], 3) +
            section('Educational Background', 'school', educationRows, 4) +
            section('Family Information', 'users', [
                ["Father's Name",       nameVal('father_name')],
                ["Father's Occupation", val('father_occupation')],
                ["Mother's Name",       nameVal('mother_name')],
                ["Mother's Occupation", val('mother_occupation')],
                ["Parent's Address",    val('parent_address')],
                ["Parent's Contact",    val('parent_contact')],
                ['Occupation',          val('occupation')],
                ['Disability',          val('disability')],
                ['PWD ID',              val('pwd_id')],
            ], 5) +
            section('Supporting Documents', 'files', documentRows, 5);

        const container = document.getElementById('reviewSummary');
        if (container) {
            container.innerHTML = html;
            container.querySelectorAll('[data-edit-step]').forEach(function (button) {
                button.addEventListener('click', function () {
                    goTo(Number(button.getAttribute('data-edit-step')), true);
                });
            });
            if (window.lucide) lucide.createIcons({ nodes: [container] });
        }
    }

});
