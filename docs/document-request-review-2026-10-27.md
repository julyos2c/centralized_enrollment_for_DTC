# Document request logic review — 27 October 2026

Reviewed the current student/appointment and alumni document flows, shared booking service, portal payment routing, Cashier processing, Registrar transitions, cancellation, and role middleware. Earlier uncommitted work was preserved.

## Fixes

1. **Quantity serialization:** student and alumni summaries could display quantities even when restored checkbox/input disabled states disagreed. Both wizards now synchronize quantity and stepper controls with checkbox selection on refresh and immediately before advancing/submitting. Required quantities are still enforced server-side; missing values are not silently defaulted. Validation now calls the field “number of copies” instead of displaying its internal array path. Browser reproduction of the original screenshot remains unverified; restored-state behavior is covered by JavaScript tests.
2. **Sparse selections:** browser forms use indexes tied to document options. Selecting options 1 and 2 without option 0 is valid, but both controllers read item 0. Selected lists are now reindexed after validation, before reading their first item. Feature tests cover student and alumni selections that omit the first option.
3. **Optional GCash receipt:** bundle verification allowed an omitted receipt number but later accessed the missing array key. It now consistently stores null when omitted. Walk-in receipt requirements remain.
4. **Bundle integrity:** older per-document payment endpoints could independently verify/reject/resubmit a member of a bundle. This could desynchronize its shared payment record and remaining items. Those mutations now require the bundle endpoint for both requesters and Cashier/Admin. Standalone historical requests retain their existing processing routes.

## Role and lifecycle review

| Role | Current scope |
| --- | --- |
| Student | Own document bookings, payment details/proofs, correction/resubmission and cancellation within existing lifecycle constraints. Cannot verify payments or change Registrar document statuses. |
| Alumni | Own document requests and bookings; uses the same shared bundle booking/payment model when pickup is selected. Cannot verify payments or change Registrar statuses. |
| Registrar | Confirms/manages bookings and advances documents submitted → processing → ready → released. Release requires Cashier-verified payment. Cannot verify bundled payments through either Cashier routes or its older appointment-payment endpoint. |
| Cashier | Reviews proofs and verifies exact amounts, including one payment covering all bundled requests. Cannot change Registrar document-processing status. |
| Admin | Can perform both staff workflows through their existing authorized routes. Subject to the same amount, bundle and transition validation. |
| New applicant | Currently shares appointment booking routes with students/alumni. Alumni-only document-history routes remain forbidden. Whether pre-enrollment applicants should request academic documents needs a product-policy decision. Access was not changed during this review. |

Booking uses the slot lock and checks capacity, closures, past dates and duplicate active bookings. Fees are read from server settings and saved per request; bundle totals are reconciled against their linked request fees. Owners are checked for portal payment/proof operations. Existing cancellation preserves processing/history according to existing rules; a cancellation is not a refund. These checks are automated/source evidence, not a complete concurrent-load or browser audit.

## Remaining decisions and limitations

- **Zero fees:** settings allow zero document fees, but Cashier's amount inputs/validation require at least 0.01. A zero-total request therefore cannot complete the current payment/release sequence. Define the intended free-document/waiver process before enabling zero fees; no automatic payment verification or new financial policy was introduced.
- **Applicant eligibility:** decide whether new applicants should retain document-booking access and whether document types require additional academic eligibility checks. Current routing alone does not establish a graduation requirement for requesting a diploma.
- Full PHP run: **426 passed, 1 failed out of 427**. The failure is `AcademicIdentityTest::student_dashboard_shows_enrolled_ay_pill_when_profile_has_ay`, which expects the previous dashboard academic-year presentation. The document-flow changes do not modify that profile section. A later added role-matrix test was verified in the targeted run below.
- Expanded relevant PHP run: **95 passed; 737 assertions** across AppointmentFlow, DocumentRequestPaymentTracking, AppointmentEditingSafety, PortalPaymentPages, and AppointmentScheduling tests.
- Complete JavaScript suites: **68 passed, 0 failed**, including restored quantity-state regressions for both wizards.
- Blade compilation, modified PHP/JavaScript syntax checks, and whitespace checks passed (existing CRLF conversion warnings remain).
- Real browser serialization and end-to-end pickup/payment interactions still need manual checking. No production mail delivery, HTTPS, distributed workers or concurrent load was tested. No files were staged, committed or deployed.
