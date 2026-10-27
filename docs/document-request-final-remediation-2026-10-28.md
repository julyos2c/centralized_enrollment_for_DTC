# Document workflow final remediation — 28 October 2026

## 1. Root cause analysis

**Zero-fee requests:** creation always selected a financial payment method and created a bundle payment, while release checked only `is_paid` plus `payment_status=verified`. Cashier correctly required positive received amounts, so a legitimate zero fee could never satisfy release. Payment validation also required GCash data before considering the authoritative fee.

The shared pricing service now quotes validated types/quantities from server settings in centavos. Zero totals need no receipt, reference, proof, cash collection or payment transaction. Model predicates distinguish “No Payment Required” from a paid request; Registrar processing uses the shared fulfillment predicate. Free items keep `is_paid=false`, no verification timestamp and no amount received. An audit entry explains the zero-fee basis. Positive-fee Cashier checks were not weakened.

**Applicant access:** requester appointments and payment pages inherited the broad portal role group. Nested Student/Alumni role middleware now protects document pages, booking mutations, payment initiation, proofs and QR endpoints. Alumni-only routes remain restricted. Search and navigation omit document actions for applicants, and applicant transaction history does not expose document/appointment records. Staff notifications for historical applicant-owned requests use the dashboard rather than a newly restricted requester URL.

**Existing dashboard test:** `AcademicIdentityTest::student_dashboard_shows_enrolled_ay_pill_when_profile_has_ay` still fails in isolation. Its fixture stores `student_profiles.enrolled_ay=2025-2026` and expects that value plus the former “Enrolled AY” label. The current profile partial displays the configured/current enrollment year under “Academic year” and does not fall back to the profile's stored enrolled year. This display/expectation mismatch predates this remediation. It is not caused by document pricing, applicant restrictions, shared test state or the wall clock. Neither the dashboard nor that test was changed to force a pass.

## 2. Files modified

The worktree already contained substantial document, dashboard and UI edits. This list identifies files touched by this remediation; unrelated edits were retained, and the earlier four fixes remain covered.

| File | Purpose |
| --- | --- |
| `app/Services/DocumentRequestPricing.php` (new) | Shared authoritative pricing, bounds/type/quantity validation and auditable no-payment explanation. |
| `app/Services/DocumentAppointmentBookingService.php` | Authorize requester roles; use authoritative quotes; omit zero-total payment records; distinguish free items within mixed bundles. |
| `app/Models/DocumentRequest.php` | `paymentNotRequired()` and `paymentSatisfied()` domain rules. |
| `app/Models/Appointment.php` | Recognize bookings whose linked documents all require no payment, without treating them as paid. |
| `app/Http/Controllers/Student/AppointmentController.php` | Quote before requiring payment data; free-request feedback; positive-only bundle resubmission; protect legacy payment paths and free/payable type changes. |
| `app/Http/Controllers/Alumni/DocumentRequestController.php` | Same pricing/validation, free standalone audit, short-window retry protection and free pickup eligibility. |
| `app/Http/Controllers/Cashier/DocumentPaymentController.php` | Keep free requests outside payable work; verify only payable bundle items; block zero-fee financial mutations; preserve existing paid records. |
| `app/Http/Controllers/Registrar/DocumentRequestController.php` | Permit authorized free fulfillment, audit legacy free cases and provide accurate ready/notification messages. |
| `app/Http/Controllers/Registrar/AppointmentController.php` | Free ready-request eligibility, legacy pricing/audit, shared bundle notifications and safe applicant destinations; retain Cashier-only bundle verification. |
| `app/Http/Controllers/PortalPaymentController.php` | Read-only No Payment Required state, with no payment form. |
| `app/Http/Controllers/PortalDashboardController.php` | Count free ready documents correctly; remove inactive pre-enrollment document action. |
| `app/Http/Controllers/SearchController.php` | Exclude unauthorized document-booking pages from applicant search results. |
| `app/Services/TransactionHistory.php` | Exclude free requests from financial transactions and restrict applicant history to enrollment. |
| `app/Services/AppointmentCancellationService.php` | Avoid unnecessary Cashier notices for entirely free requests; keep historical applicant notification destinations safe. |
| `routes/web.php` | Student/Alumni middleware on requester appointment and document payment routes. |
| `resources/views/partials/navbar.blade.php` | Applicant eligibility marker for search suggestions. |
| `public/js/dtc-app.js` | Hide the restricted appointment suggestion for applicants. |
| `public/js/student-booking.js` | Show free selection state, hide payment choices/proof and skip unnecessary payment validation. |
| `resources/views/student/appointments/create.blade.php` | No-payment notice and payment-choice hooks. |
| `resources/views/alumni/requests/create.blade.php` | Same free-state behavior in the alumni wizard. |
| `public/css/document-request-flow.css` | Theme-consistent no-payment notice and hidden-state behavior. |
| `resources/views/student/appointments/index.blade.php` | Free booking status and pickup eligibility/instructions. |
| `resources/views/alumni/requests/index.blade.php` | Free document status and pickup eligibility/instructions. |
| `resources/views/portal/alumni/dashboard.blade.php` | Accurate free-document badges and next actions. |
| `resources/views/portal/payments/details.blade.php` | No Payment Required explanation without payment actions. |
| `resources/views/registrar/documents/index.blade.php` | Free status and release-control eligibility. |
| `resources/views/registrar/appointments/index.blade.php` | No Payment Required indicator. |
| `resources/views/cashier/document-payments/show.blade.php` | Read-only free status without verification controls. |
| `tests/Feature/DocumentRequestRemediationTest.php` (new) | Deterministic free/mixed pricing, authorization, role transitions, tampering, retry and fulfillment regressions. |
| `tests/Feature/AppointmentFlowTest.php` | Give a financial-notification fixture an explicit positive fee; preserve existing bundle regressions. |
| `tests/Feature/PortalPaymentPagesTest.php` | Replace the old applicant-access expectation with Student/Alumni expectations; negative applicant coverage is in the new test class. |
| `tests/js/document-quantity-submission.test.cjs` | Free notices, payment-choice visibility and summary updates alongside quantity/copy-amount regressions. |
| `docs/document-request-final-remediation-2026-10-28.md` (new) | This report and manual-QA plan. |

## 3. Workflow changes

- An entirely free booking creates its normal document records and appointment, but **no `AppointmentPayment` record**. A free standalone alumni request creates no financial record either.
- Free requests retain the existing schema's `unpaid`/`walk_in` storage defaults as non-financial metadata; UI and fulfillment decisions use the explicit domain predicates. They are not marked paid or verified. `not_required` is a presentation value, not a new database status.
- Mixed bundles charge only their positive total. Their zero-fee members stay free through rejection, resubmission, verification and release. Releasing a free member does not block paying the remaining payable members.
- Stored request fees remain authoritative after creation. Later price-setting changes do not retroactively waive or reprice existing requests. Already verified payments stay verified and remain in history.
- Pending edits that would cross between a free and payable workflow require a new request; existing money/proof records are not silently rewritten.
- Existing slot locking and duplicate-active-booking checks remain. Identical submitted free standalone requests from the same user/purpose/quantity within one minute reuse the existing record under a user-row lock. This is short-window retry protection, not indefinite idempotency.
- Registrar still controls submitted → processing → ready → released. Alumni pickup readiness/confirmation rules remain; release requires either verified payment or a legitimately stored zero fee.

## 4. Role authorization matrix

| Role | Create through requester portal | View | Process | Verify payment | Release |
| --- | --- | --- | --- | --- | --- |
| Student | Own eligible requests | Own bookings/payment/proofs | No | No | No |
| Alumni | Own eligible requests | Own requests/bookings/payment/proofs | No | No | No |
| New Applicant | No | No Student/Alumni document functionality; own enrollment access retained | No | No | No |
| Registrar | Existing staff/legacy operations only | Authorized staff records | Yes | No bundled-document payment verification | Yes, only when fulfillment requirements are met |
| Cashier | No requester creation by default | Authorized financial records | No | Positive payable requests/bundles only | No |
| Admin | Existing authorized staff operations | Authorized oversight | Yes | Positive payable requests/bundles only | Yes, same fulfillment requirements |

Request ownership checks remain in addition to role middleware. Role transitions are checked on later requests. Historical records are not deleted when a role loses requester access; authorized staff can still process them. A document request is not a new academic eligibility policy: additional institution-specific restrictions on particular document types were not invented.

## 5. Test results

Final command output is recorded in `storage/logs/document-remediation-release-full.txt`, `storage/logs/document-remediation-final-focused.txt`, `storage/logs/document-final-js.txt`, and `storage/logs/document-academic-year-isolated.txt`.

- Full PHP suite: **441 passed, 1 failed, 0 skipped; 442 tests, 3,755 assertions**. The only failure is the independent academic-year mismatch described above.
- Focused document/payment PHP suite: **116 passed, 0 failed, 0 skipped; 889 assertions**.
- Complete JavaScript suites: **68 passed, 0 failed, 0 skipped**.

The existing academic-year failure is reproducible alone: **0 passed, 1 failed, 0 skipped; 2 assertions**. It is retained and reported, not suppressed.

PHP syntax checks, route middleware inspection, JavaScript syntax checks and Blade compilation were run. `git diff --check` reports no whitespace errors; Git still warns that several LF files will be converted to CRLF on a later write.

## 6. Security review

- Browser fees, totals, discounts, waiver flags and paid flags do not control pricing or exemption. Server-configured prices and validated types/quantities do.
- Invalid/negative/sub-cent prices are rejected; duplicates and invalid quantities are rejected; quote totals fit the existing Cashier amount limit.
- Slot/request/payment locks preserve existing booking and verification consistency. No positive amount check was relaxed to accept invented zero payments.
- No exemption endpoint or client-writable exemption field was introduced. Paid records do not silently become exempt after a settings change.
- Applicant direct URLs, forged AJAX submissions, payment/proof routes, edits, cancellation and history are denied server-side. Student and alumni ownership checks still reject other users' records.
- Free queries use scalar predicates and existing eager-loaded document collections in listing views. Pricing settings are fetched together. No new per-row pricing lookup was added.
- Earlier quantity-state, sparse-index, optional-receipt and single-item bundle-bypass fixes remain intact and covered by regression tests.
- No authentication/session changes, CAPTCHA changes, new dependencies or new migrations were introduced in this remediation. Nothing was staged, committed, pushed or deployed.

## 7. Remaining risks and manual verification

Manual browser verification is **pending**. Prepared checks:

| Actor | Browser scenario |
| --- | --- |
| Student | Book free single/multiple documents; confirm No Payment Required, no proof demand, correct summary and no financial entry. Repeat paid cash/GCash and mixed bundles; verify quantity restoration, QR saving and copying exact amounts. |
| Alumni | Repeat free standalone and pickup workflows; verify readiness before pickup, no receipt demand for free requests, retry protection, existing paid history and GCash resubmission. |
| New Applicant | Attempt direct booking/payment/proof/history URLs and forged requests; confirm denial while application/enrollment/payment access remains. Check navigation/search does not offer document booking. |
| Registrar | Process free requests through each valid status; reject skipped/backward transitions; confirm free ready pickup; verify positive unpaid requests still cannot be released. |
| Cashier | Verify paid cash/GCash and mixed bundles with exact amounts; reject mismatches; confirm free requests have no collection/verification form and free bundle members stay distinct. |
| Admin | Repeat both staff workflows and verify audit reasons, ownership boundaries and historical records after a role change. |

This remains a single-server local environment. Production HTTPS, mail delivery, multi-worker/cache deployment behavior and concurrent load have not been claimed tested. The short-window standalone retry rule may need an explicit user-visible idempotency token if the institution later requires indefinite retry deduplication.

## 8. Release readiness

**READY FOR MANUAL QA.** The document-domain regressions are passing in focused verification. This is not approval for merge or production: the independent academic-year display/test mismatch and manual/browser/deployment gates remain open.
