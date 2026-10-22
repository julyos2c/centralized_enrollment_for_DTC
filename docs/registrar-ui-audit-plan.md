# Registrar UI audit and redesign plan

Date: 2026-10-23. Source inspection only; layout findings need browser confirmation. Dashboards are excluded. Programs and Subjects are included as an additional administration area requested by the user.

## Implementation status

The registrar redesign is now applied through `public/css/registrar-workspace.css` and explicit view markup. It covers applications, enrollments, document requests, appointments, slots, schedules, academic records and imports. Dashboards do not load the new stylesheet.

Mobile queue tables now use labeled cards, document status controls wrap, application details align left, slot actions have consistent touch targets, and import uploads have clearer controls. Row selectors bind to explicit tables; applications and academic records keep theirs inside existing filter forms. Application AJAX requests preserve the selected row count.

Validation: Blade compilation passed; the registrar/appointment/academic PHP selection passed (96 tests, 486 assertions); the new layout tests passed (2 tests, 30 assertions); rows-per-page JavaScript tests passed (4 tests). Browser verification of mobile layouts, dialogs, uploads and action flows remains required. The audit below records the original findings and proposed scope, not a claim that every visual detail has been browser verified.

## Audit findings

| Priority | Area / files | Source evidence | Proposed treatment |
|---|---|---|---|
| 1 | `registrar/appointments/index.blade.php` | Header places description and Manage Slots in a single non-wrapping flex row. Pending payment and appointment tables contain many columns and separate controls. | Wrapping page heading, full-width mobile action, explicit table identities, compact review cards with identity/status, booking details, then actions. Keep proof and payment-verification forms intact. |
| 1 | `registrar/appointments/schedule.blade.php` | Closure calendar is a seven-column table; creation/impact preview and calendar have distinct density. | Separate Create Closure and Calendar sections, responsive field grouping, labeled mobile closure records, predictable Deactivate placement. Keep impact preview, confirmation and date/time rules. |
| 1 | `registrar/applications/index.blade.php` | Shared filters coexist with generic row-control relocation; AJAX builds a fresh query from search/type/status/program, without the selected row count. | Put controls directly in the existing filter form. Preserve row count in AJAX/pagination requests. Use explicit mobile card slots for applicant, reference/program, status and Review. |
| 1 | `registrar/documents/index.blade.php` | Separate inline style block, custom search/status chips and table controls; Update Status uses its own modal. | Align toolbar with other registrar queues; consistent wrapping status controls, requester/document hierarchy, request-status row and one action footer. Retain the existing workflow transitions and remarks. |
| 2 | `registrar/enrollments/index.blade.php` | Standalone row-control dropdown, dense identity/program/year/status/actions table. | Responsive list card with explicit identity/status placement, grouped academic details and a single Review action. Place row control in a compact explicit list-options area. |
| 2 | `registrar/enrollments/show.blade.php`, `_review-content.blade.php`, `_review-modal.blade.php` | Standalone and modal hosts share detailed content, including nested decision dialogs. | Keep one shared content implementation; simplify section hierarchy and balance mobile actions. Verify scrolling/focus in nested dialogs. Preserve approval/rejection behavior and subject review. |
| 2 | `registrar/applications/show.blade.php` | Long detail page, inline styles, document action rows and several state-dependent action forms. | Group identity/status, academic details, documents, family information and decision actions consistently. Wrap long references and document labels; retain every applicable decision and cancellation control. |
| 2 | `registrar/students/index.blade.php` | Heading actions use adjacent inline links rather than a defined action group. Row limits still use automatic relocation. | Explicit wrapping heading actions, filter-contained row selector, consistent mobile record cards. Preserve Student/Alumni scope and provisioning restrictions. |
| 2 | `registrar/students/form.blade.php`, `provision.blade.php` | Dense form markup mixes explanatory copy, validation, account implications and actions. | Group identity, academic and contact fields; short contextual instructions; equal mobile form actions with visible errors. Preserve optional fields, immutable student number and invitation rules. |
| 3 | `registrar/students/show.blade.php` | Separate `ar-*` heading/history pattern; multiple independently paged collections and generic row controls. | Retain academic-record identity; standardize section spacing and history cards. Each history table needs an explicit control association; do not merge their records or counts. |
| 3 | `registrar/imports/index.blade.php`, `show.blade.php` | Upload form, preview, confirmation, report links and bulk provisioning combine several independent action groups. Existing mobile table styling does not address page-level hierarchy. | Three clear stages: Upload, Validate, Confirm. Distinguish preview from committed records; group invitation/bulk actions separately. Keep checkbox selection, all validation details and private report access. |
| 3 | `registrar/appointments/slots.blade.php`, `public/css/registrar-slots.css` | Long header actions compete with the title; desktop/tablet/mobile tracks differ. | Wrap title/actions deliberately, give mobile actions a consistent grid, keep badges in one area and leave slot date/time/capacity readable. Preserve add/edit/delete/close controls. |
| Added scope | `admin/programs/index.blade.php`, `subjects.blade.php`, `partials/subjects-card.blade.php` | Generic rows-per-page includes create Filters dropdowns despite no business-data filter form. Subject lists already have program selection and year/semester grouping. | Remove these dropdowns. Keep program selection, server pagination, grouping, schedules and management dialogs. Further visual work should focus on title length, equal action sizing and program/subject identity. |

Paths above are relative to `resources/views/` unless otherwise stated. Existing shared styles include `public/css/dtc-theme.css`, `dtc-filters.css`, `registrar-slots.css` and recently added `.dtc-mobile-records` / `.dtc-balanced-footer` rules. Extend their conventions with registrar-scoped selectors; do not introduce another global table/modal override.

## Applied now: Programs and Subjects filter removal

Removed the generic table-rows partial from:
- `resources/views/admin/programs/index.blade.php`
- `resources/views/admin/programs/partials/subjects-card.blade.php`
- `resources/views/admin/programs/subjects.blade.php`

There was no separate business-data filter to remove. The `$filtered` collection in the grouped subjects view is year/semester organization, not an interactive filter; it remains. Pagination, AJAX subject selection and all create/edit/delete/schedule forms remain unchanged.

## Redesign sequence

1. Establish a registrar-only page wrapper and scoped stylesheet for non-dashboard pages. Define wrapping headers, consistent spacing, readable badge placement, 44px mobile actions, and predictable list/detail footers. Do not change global dashboard rules.
2. Redesign Applications, Enrollments, Documents and Appointment Requests using one queue pattern: search/filter toolbar; explicit row control where appropriate; desktop table; mobile identity/status block, details and aligned actions; count/pagination below.
3. Redesign slot management and office closures with grouped scheduling fields and labeled mobile records. Avoid hiding dates, capacity, impacts or action choices.
4. Redesign review/detail pages and their shared modal content. Use concise headings, consistent label/value pairs and scrollable modal bodies with reachable actions. Preserve linked account, original-document, cancellation and acknowledgement information.
5. Redesign records, provisioning and imports. Keep data editing distinct from account creation and invitations; retain bulk selections and validation outcomes.
6. Polish Programs/Subjects around the simplified navigation, without restoring Filters dropdowns.

## Verification plan

- Test at 320, 375, 430, 768 and 1280 CSS pixels in light/dark themes; include long names, program names, references and translated/wrapped status labels.
- Verify empty, single-record and multi-page lists, filters, AJAX swaps and page reloads. Confirm row settings never attach to a different table.
- Verify record access and mutations for registrar/admin, and forbidden access for unrelated roles. Retain CSRF/session-expiry handling; never auto-retry decisions or payment requests.
- Exercise application decisions/cancellations, enrollment review, document transitions, appointment payment proof/verification/rejection, slot changes and closure impact checks.
- Exercise academic-record editing, import preview/commit/cancel/report access and bulk invitation selections.
- Exercise program selection, subject pagination and CRUD/scheduling dialogs after AJAX refreshes.
- Run the related feature suites, JavaScript regression tests, Blade compilation and structural checks. Record browser testing separately from automated verification.

No registrar/dashboard or other dashboard changes are part of this plan. No backend schema, payment policy, permission or workflow changes are proposed.
