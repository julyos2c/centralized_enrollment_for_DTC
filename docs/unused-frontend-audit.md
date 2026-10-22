> Historical audit: confirmed cleanup was applied on 2026-10-22. See [cleanup results](frontend-cleanup-results.md) for deletions, preserved items and validation. Candidate lists and line numbers below describe the pre-cleanup tree.

# Unused frontend audit ? 2026-10-22

Read-only source audit of app/, config/, routes/, resources/, public/js/ and public/css/. No application files were changed. Vendor packages, generated assets, compiled views, backups, database-supplied HTML and browser runtime coverage are not exhaustively audited. ?Safe? below means no current application consumer was found, not proof against external consumers or future features. No tests were needed for this report-only change.

## High-confidence removal candidates

| Item | Evidence and removal boundary |
|---|---|
| public/css/appointments-create.css (9,283 bytes) | No asset reference found. Booking uses other styles. Safe to remove as an unused local asset. |
| public/js/appointments-create.js (11,261 bytes) | Only external filename reference is a comment in student-booking.js saying it replaces this script. No script loading reference; obsolete step-panel/summary/walkin-details markup is absent. Safe to remove this file; retain student-booking.js. |
| public/css/application-wizard.css:1874?2159 | Removed location-picker component: .loc-picker-* and .loc-tab* have no current template or script consumer. Remove component rules and their otherwise-unreferenced animations together, preserving surrounding shared rules. |
| public/css/application-wizard.css:311,2517,2534,2634 | .opt label styles have no current class consumer in the application form. Remove only the .opt selectors/rules; preserve other selectors if a rule is shared. |
| public/js/application-wizard.js:261?262,309?327 | confirmSubmit/confirmCancel elements do not exist. Their variable declarations and guarded listener blocks are obsolete. Keep shared dtcConfirmModal integration and window.dtcConfirm.open. |
| resources/views/admin/users/create.blade.php | No controller/include/route renders this view; current UserController renders index and creation uses its modal. Safe for current route graph. |
| resources/views/layouts/guest.blade.php | No current extends/include consumer found. Safe for current source graph. |
| resources/views/partials/pagination-summary.blade.php | Only reference is its own usage-example comment. Safe for current source graph. |
| resources/views/partials/ui-polish.blade.php | No include consumer found. Its inline CSS is not currently shipped. Safe for current source graph; removal will not apply its intended styling. |
| resources/views/components/dtc/file-upload.blade.php | No x-dtc.file-upload or explicit view consumer found. Safe for current source graph; other upload implementations still exist. |
| resources/views/components/dtc/loading-button.blade.php | No x-dtc.loading-button or explicit view consumer found. Safe for current source graph. |
| resources/views/admin/users/index.blade.php.bak | Backup is not a Blade template render target. Safe at runtime, but keep it if it is your only recovery copy. |

## Conditional candidates ? do not delete automatically

- public/css/dtc-dark-global.css is empty but configured in config/adminlte.php:420. Remove its plugin file entry together with the file to avoid a 404. No CSS payload saving today.
- resources/views/portal/guidance/{announcements,enrollment-steps,help,payments,programs,requirements}.blade.php have no current route/controller consumer. They appear to be dormant features. Safe for the current route graph, but confirm that these pages are not planned before removing them.
- components/dtc/page-header.blade.php and empty-state.blade.php are used by those dormant guidance pages. Remove only if those pages are retired; they are not standalone unused components.
- resources/views/errors/layout.blade.php has no explicit local consumer. Laravel error templates use namespace lookup and package fallback; verify representative 403/404/419/500 responses before removing an error layout.
- resources/views/auth/_auth-layout.css has no source reference. It appears to be an unconsumed source copy; auth pages load public/css/auth-pages.css. Preserve if this is the intended authoring source.
- document-request.js has a drForm branch but is loaded on request list pages, while alumni/requests/create.blade.php implements its own inline behavior. The form branch appears stale for current loading paths. Keep the active list/filter behavior and verify both student and alumni request flows before removing only the form branch.
- application-wizard.js LOC_NAMES contains legacy PSGC code fields and obsolete cascade comments. Those hidden fields still exist and backend code validation remains active. Do not remove code-field handling or backend rules merely because the modal was removed.

## Defect discovered, not dead code

application-wizard.js:229,247,254,269,285 reads acknowledgement and assigns hidAck.value, but no such input exists. The real checkbox is ack_enrollment, which is what the controller validates. These obsolete writes can throw on submission/consent changes. Remove the obsolete variable and writes in a focused fix, preserving ack_enrollment validation, submission protection and the shared confirmation dialog. Do not remove the whole submit handler.

## Keep despite naive unused-search results

- All other public/js assets have source references; a reference proves loading, not that every branch executes.
- dtc-stat-* colors, notif-icon-* and is-* status selectors are assembled dynamically. Keep them.
- Cropper, Bootstrap, AdminLTE and OverlayScrollbars selectors can originate in packages or runtime DOM. Keep them pending runtime coverage.
- Named init functions in dtc-app.js are immediately invoked function expressions, not unused functions.
- resources/views/vendor/adminlte overrides use adminlte:: names and dynamic menu includes. Ordinary dotted-view searches cannot establish that they are unused.
- resources/css/app.css and resources/js/app.js are Vite entry points; do not delete based on missing direct HTML references.
- application.css, welcome.css and dtc-theme.css contain candidate blocks but also active styles. Do not delete these files wholesale.

## CSS selector candidates requiring review

The following inventory lists class/ID tokens absent literally from non-CSS application source. It is a broad candidate list, NOT a deletion list. It includes known dynamic/vendor false positives noted above. A rule is removable only when its whole selector list and runtime consumers are checked. Some entries belong to intentionally reusable components. Line numbers refer to the current working tree and may move.

### public/css/application-success.css

- `application-reference-heading` ? line 18

### public/css/application-wizard.css

- `app-wizard-brand` ? line 55
- `app-wizard-brand-label` ? line 56
- `app-wizard-brand-title` ? line 60
- `app-wizard-progress-label` ? line 62
- `app-wizard-bar-wrap` ? line 153
- `app-wizard-bar` ? line 153
- `wiz-notice` ? line 204
- `wiz-row--3` ? line 295
- `wiz-row--4` ? line 296
- `wiz-col-2` ? line 297
- `wiz-check-label` ? line 525
- `wiz-check-box` ? line 536
- `wiz-check-error` ? line 563
- `loc-select-wrap` ? line 813
- `loc-select` ? line 813
- `loc-loading` ? line 861
- `loc-autofilled` ? line 872
- `loc-postal-hint` ? line 877
- `loc-summary` ? line 891
- `rev-edit-btn` ? line 1047
- `priv-card` ? line 1067
- `priv-card-head` ? line 1074
- `priv-card-title` ? line 1088
- `priv-card-sub` ? line 1094
- `priv-card-body` ? line 1100
- `priv-card-note` ? line 1114
- `dpn-card` ? line 1168
- `dpn-head` ? line 1176
- `dpn-head-icon` ? line 1184
- `dpn-title` ? line 1193
- `dpn-subtitle` ? line 1199
- `dpn-body` ? line 1206
- `dpn-grid` ? line 1215
- `dpn-grid-item` ? line 1221
- `dpn-grid-icon` ? line 1230
- `dpn-terms-note` ? line 1256
- `dpn-foot` ? line 1277
- `dpn-sign-label` ? line 1284
- `dpn-sign-text` ? line 1336
- `consent-scroll` ? line 1391
- `consent-checks` ? line 1392
- `consent-check-label` ? line 1393
- `consent-check-text` ? line 1394
- `consent-head` ? line 1395
- `confirm-modal-overlay` ? line 1613
- `confirm-modal-header` ? line 1647
- `confirm-modal-icon` ? line 1655
- `confirm-modal-title` ? line 1666
- `confirm-modal-body` ? line 1673
- `confirm-modal-sub` ? line 1688
- `confirm-modal-footer` ? line 1693
- `wiz-row--name` ? line 1761
- `name-group` ? line 1775
- `name-addon` ? line 1793
- `name-group--prefix` ? line 1775
- `name-group--suffix` ? line 1776
- `loc-picker-trigger` ? line 1874
- `loc-picker-trigger-icon` ? line 1896
- `loc-picker-trigger-chevron` ? line 1898
- `loc-picker-modal` ? line 1901
- `loc-picker-backdrop` ? line 1912
- `loc-picker-sheet` ? line 1923
- `loc-picker-header` ? line 1948
- `loc-picker-title` ? line 1956
- `loc-picker-nav-btn` ? line 1964
- `loc-picker-tabs` ? line 1981
- `loc-tab` ? line 1988
- `loc-picker-search-wrap` ? line 2014
- `loc-picker-search-icon` ? line 2019
- `loc-picker-search` ? line 2014
- `loc-picker-list` ? line 2043
- `loc-picker-group-label` ? line 2052
- `loc-picker-item` ? line 2066
- `loc-picker-item-check` ? line 2087
- `loc-picker-loading` ? line 2095
- `loc-picker-empty` ? line 2096
- `loc-picker-spinner` ? line 2106
- `loc-picker-retry` ? line 2138
- `app-wizard-brand-copy` ? line 2167
- `status-lookup-secure` ? line 2219
- `is-submitted` ? line 2231
- `is-under_review` ? line 2232
- `is-revision_required` ? line 2233
- `is-approved` ? line 2234
- `is-completed` ? line 2250
- `is-action_required` ? line 2252
- `is-skipped` ? line 2257

### public/css/application.css

- `public-main` ? line 21
- `application-page` ? line 29
- `application-container` ? line 34
- `application-header` ? line 44
- `application-card` ? line 74
- `application-card-header` ? line 83
- `application-section` ? line 104
- `application-section-title` ? line 108
- `application-section-description` ? line 124
- `form-grid` ? line 134
- `form-grid-3` ? line 140
- `form-select` ? line 176
- `application-alert` ? line 224
- `application-actions` ? line 260
- `btn-application-primary` ? line 269
- `btn-application-secondary` ? line 298

### public/css/appointments-create.css

- `dtc-appt-steps` ? line 4
- `dtc-appt-step-circle` ? line 16
- `dtc-appt-step-label` ? line 29
- `dtc-doc-desc` ? line 86
- `dtc-empty-slot-state` ? line 134
- `dtc-pay-option` ? line 145
- `dtc-pay-method-card` ? line 153
- `dtc-pay-detail-panel` ? line 157
- `dtc-review-summary` ? line 165
- `dtc-review-row` ? line 171
- `dtc-review-label` ? line 180
- `dtc-review-value` ? line 191

### public/css/appointments-index.css

- `appt-pay-strip` ? line 4
- `appt-card` ? line 33

### public/css/document-request-flow.css

- `dr-alert--ok` ? line 83
- `dr-day` ? line 376
- `dr-day-title` ? line 379
- `dr-date-field` ? line 380
- `dr-time-days` ? line 381
- `dr-time-grid` ? line 382
- `dr-time-opt` ? line 383
- `appointment-slot-fallback` ? line 392
- `dr-when` ? line 433
- `dr-when-leaf` ? line 434

### public/css/document-requests.css

- `dtc-document-create` ? line 2
- `dtc-request-main` ? line 2
- `dtc-request-aside` ? line 3
- `dtc-request-guide` ? line 5
- `dtc-request-guide-list` ? line 7
- `dtc-request-handoff` ? line 10
- `dtc-request-fee-summary` ? line 11
- `dtc-request-fee-summary__title` ? line 12
- `dtc-request-submit` ? line 19
- `dtc-request-section-heading` ? line 20
- `dtc-request-section-number` ? line 22
- `dtc-request-step-number` ? line 23
- `dtc-payment-method` ? line 24
- `dtc-document-request-row` ? line 28
- `dtc-document-request-meta` ? line 29
- `dtc-request-status-note` ? line 30
- `dtc-request-resubmit` ? line 33
- `dtc-staff-request-help` ? line 36
- `dtc-request-amount` ? line 37
- `dtc-request-fee-list` ? line 38
- `dtc-student-document-create` ? line 39
- `dtc-pay-method-card` ? line 43
- `dtc-pay-option` ? line 43
- `appt-pay-strip` ? line 44
- `appt-card` ? line 45
- `dtc-empty-slot-state` ? line 46
- `dtc-appt-step-label` ? line 59
- `dtc-review-row` ? line 64
- `dtc-review-value` ? line 65
- `dtc-document-request-main` ? line 69
- `dtc-document-request-heading` ? line 70
- `dtc-appointment-heading` ? line 71
- `dtc-appointment-actions` ? line 72
- `dtc-request-payment-details` ? line 80
- `dtc-document-page-heading` ? line 81
- `dtc-appt-step-circle` ? line 85

### public/css/dtc-search.css

- `highlighted` ? line 206

### public/css/dtc-theme.css

- `sidebar-footer` ? line 296
- `navbar-badge` ? line 319
- `dtc-work-queue` ? line 844
- `dtc-toolbar` ? line 845
- `dtc-toolbar-left` ? line 846
- `dtc-toolbar-right` ? line 846
- `dtc-card-body` ? line 899
- `dtc-card-header` ? line 904
- `notification-dropdown` ? line 984
- `navbar-user-info` ? line 995
- `stat-footer` ? line 1083
- `dtc-stat-primary` ? line 1192
- `dtc-stat-warning` ? line 1195
- `dtc-stat-success` ? line 1198
- `dtc-stat-danger` ? line 1201
- `dtc-stat-info` ? line 1204
- `dtc-stat-neutral` ? line 1207
- `small-box` ? line 1211
- `small-box-footer` ? line 1232
- `badge-info` ? line 1295
- `btn-xs` ? line 1366
- `form-control-sm` ? line 1390
- `info-box` ? line 1495
- `info-box-icon` ? line 1502
- `info-box-content` ? line 1509
- `info-box-text` ? line 1513
- `info-box-number` ? line 1521
- `login-page` ? line 1644
- `login-box` ? line 1648
- `login-card-body` ? line 1652
- `login-logo` ? line 1659
- `settings-tabs` ? line 1697
- `settings-tab-item` ? line 1699
- `settings-tab-icon` ? line 1714
- `settings-tab-title` ? line 1729
- `settings-tab-desc` ? line 1731
- `custom-select` ? line 2048
- `form-select` ? line 2049
- `text-dark` ? line 2073
- `list-group` ? line 2166
- `list-group-item` ? line 2167
- `os-viewport` ? line 2390
- `os-scrollbar-horizontal` ? line 2394
- `os-scrollbar-vertical` ? line 2401
- `dtc-page-header-content` ? line 2505
- `dtc-card-title` ? line 2551
- `dtc-card-subtitle` ? line 2558
- `dtc-card-footer` ? line 2568
- `dtc-card-hover` ? line 2574
- `dtc-stat-content` ? line 2589
- `dtc-stat-meta` ? line 2597
- `dtc-status-primary` ? line 2654
- `dtc-status-info` ? line 2655
- `dtc-status-success` ? line 2656
- `dtc-status-warning` ? line 2657
- `dtc-status-danger` ? line 2658
- `dtc-status-secondary` ? line 2659
- `dtc-progress` ? line 2662
- `dtc-progress-bar` ? line 2670
- `dtc-form-group` ? line 2682
- `dtc-form-select` ? line 2708
- `dtc-form-error` ? line 2745
- `dtc-input-group` ? line 2755
- `dtc-input-addon` ? line 2765
- `dtc-filter-chip` ? line 2803
- `notif-icon-success` ? line 2951
- `notif-icon-danger` ? line 2952
- `notif-icon-warning` ? line 2953
- `notif-icon-info` ? line 2954
- `dtc-upload-zone` ? line 3120
- `is-dragover` ? line 3136
- `dtc-upload-icon` ? line 3142
- `dtc-upload-title` ? line 3155
- `dtc-upload-help` ? line 3161
- `dtc-upload-list` ? line 3167
- `dtc-upload-file` ? line 3171
- `dtc-upload-file-name` ? line 3181
- `dtc-upload-file-meta` ? line 3192
- `dtc-timeline-meta` ? line 3243
- `dtc-timeline-description` ? line 3249
- `dtc-table-wrap` ? line 84
- `dtc-callout` ? line 3336
- `dtc-callout-icon` ? line 3347
- `dtc-callout-title` ? line 3352
- `dtc-callout-text` ? line 3358
- `dtc-action-list` ? line 3373
- `dtc-action-item` ? line 3379
- `dtc-action-icon` ? line 3398
- `dtc-action-content` ? line 3411
- `dtc-action-title` ? line 3416
- `dtc-action-description` ? line 3422
- `dtc-action-arrow` ? line 3428
- `navbar-menu-divider` ? line 3464
- `dtc-document-page-title` ? line 3527
- `dtc-document-page-heading` ? line 3528
- `dtc-document-page-action` ? line 3529
- `dtc-document-request-row` ? line 3530
- `dtc-document-request-main` ? line 3537
- `dtc-document-request-heading` ? line 3538
- `dtc-document-request-meta` ? line 3541
- `dtc-enrollment-track-progress` ? line 3570
- `navbar-menu-item` ? line 3702
- `sidebar-mini` ? line 3807
- `sidebar-collapse` ? line 3807
- `sidebar-mini-md` ? line 3808
- `sidebar-mini-xs` ? line 3809
- `sidebar-no-expand` ? line 3845
- `sidebar-focused` ? line 3846
- `dtc-field-error` ? line 3939
- `dtc-work-queue-card` ? line 3957
- `dtc-work-queue-label` ? line 3958
- `dtc-work-queue-value` ? line 3959
- `dtc-work-queue-note` ? line 3960
- `dtc-next-step-btn` ? line 4345
- `dtc-next-step-icon` ? line 4363
- `dtc-icon-well` ? line 4968
- `dtc-quick-action-row` ? line 4975
- `is-error` ? line 5164
- `dtc-confirm-header` ? line 5236
- `dtc-docpay-show` ? line 6748

### public/css/profile.css

- `dtc-alert-success` ? line 124
- `cropper-view-box` ? line 177
- `cropper-face` ? line 178
- `cropper-line` ? line 180
- `cropper-point` ? line 181
- `point-n` ? line 182
- `point-s` ? line 183
- `point-w` ? line 184
- `point-e` ? line 185
- `dtc-avatar-confirm-cancel` ? line 281
- `dtc-avatar-confirm-apply` ? line 286

### public/css/student-dashboard.css

- `is-in_progress` ? line 9
- `is-upcoming` ? line 10
- `is-ended` ? line 11

### public/css/utilities.css

- `u-gray-bold-sm` ? line 25
- `u-link-md` ? line 27
- `u-pad-card` ? line 42

### public/css/welcome.css

- `login-screen` ? line 910
- `login-branding` ? line 919
- `brand-row` ? line 972
- `role-row` ? line 1041
- `role-pill` ? line 1053
- `login-panel` ? line 1082
- `login-card` ? line 1094
- `card-header-logo` ? line 1113
- `user-email` ? line 1133
- `toggle-pwd` ? line 1237
- `error-box` ? line 1264
- `remember-left` ? line 1311
- `btn-next` ? line 1331
- `btn-signin` ? line 1332
- `btn-back` ? line 1380
- `card-footer-text` ? line 1490
- `reveal-left` ? line 1834
- `reveal-right` ? line 1835

## Asset reference inventory

| Asset | Bytes | Source files mentioning filename |
|---|---:|---|
| public/js/application-wizard.js | 42119 | resources/views/public/application/create.blade.php |
| public/js/appointments-create.js | 11261 | public/js/student-booking.js |
| public/js/appointments-index.js | 1797 | resources/views/student/appointments/index.blade.php |
| public/js/cashier-notification-polling.js | 5908 | resources/views/partials/navbar.blade.php |
| public/js/dashboard-avatar.js | 3521 | resources/views/portal/alumni/dashboard.blade.php, resources/views/portal/new-applicant/dashboard.blade.php, resources/views/portal/student/dashboard.blade.php |
| public/js/document-request.js | 11226 | resources/views/alumni/requests/index.blade.php, resources/views/student/appointments/index.blade.php |
| public/js/dtc-app.js | 22129 | app/Http/Controllers/Auth/ResetPasswordController.php, config/adminlte.php, resources/views/auth/welcome.blade.php, resources/views/layouts/public.blade.php |
| public/js/dtc-feedback.js | 16485 | resources/views/admin/settings/general.blade.php, resources/views/layouts/public.blade.php, resources/views/vendor/adminlte/master.blade.php, public/js/dtc-app.js |
| public/js/dtc-filters.js | 1396 | resources/views/admin/settings/audit.blade.php, resources/views/admin/users/index.blade.php, resources/views/registrar/applications/index.blade.php, resources/views/registrar/students/index.blade.php |
| public/js/dtc-name-normalizer.js | 783 | resources/views/public/application/create.blade.php |
| public/js/profile.js | 9452 | resources/views/portal/profile/show.blade.php |
| public/js/signin-modal.js | 6469 | resources/views/auth/welcome.blade.php, resources/views/layouts/public.blade.php, public/js/welcome-interactive.js |
| public/js/student-booking.js | 12459 | resources/views/student/appointments/create.blade.php |
| public/js/welcome-interactive.js | 26308 | resources/views/auth/welcome.blade.php |
| public/css/admin-users.css | 21056 | config/adminlte.php, resources/views/admin/users/index.blade.php |
| public/css/application-success.css | 14100 | resources/views/public/application/success.blade.php |
| public/css/application-wizard.css | 95860 | resources/views/public/application/create.blade.php, resources/views/public/application/status.blade.php |
| public/css/application.css | 10206 | resources/views/layouts/public.blade.php |
| public/css/appointments-create.css | 9283 | None |
| public/css/appointments-index.css | 3620 | resources/views/student/appointments/index.blade.php |
| public/css/appointments-shared.css | 1878 | resources/views/student/appointments/index.blade.php |
| public/css/auth-pages.css | 12461 | resources/views/auth/forgot-password.blade.php, resources/views/auth/portal-activation.blade.php, resources/views/auth/request-activation.blade.php, resources/views/auth/reset-password.blade.php, resources/views/auth/set-password.blade.php |
| public/css/document-request-flow.css | 35627 | resources/views/alumni/requests/create.blade.php, resources/views/alumni/requests/index.blade.php, resources/views/student/appointments/create.blade.php, resources/views/student/appointments/index.blade.php |
| public/css/document-requests.css | 8259 | resources/views/student/appointments/index.blade.php |
| public/css/dtc-dark-global.css | 0 | config/adminlte.php |
| public/css/dtc-filters.css | 3073 | resources/views/admin/settings/audit.blade.php, resources/views/admin/users/index.blade.php, resources/views/cashier/payments/index.blade.php, resources/views/registrar/applications/index.blade.php, resources/views/registrar/students/index.blade.php |
| public/css/dtc-search.css | 14238 | config/adminlte.php |
| public/css/dtc-theme.css | 231893 | app/Providers/AppServiceProvider.php, config/adminlte.php, resources/views/auth/welcome.blade.php, resources/views/layouts/public.blade.php, resources/views/partials/ui-polish.blade.php, resources/views/vendor/adminlte/partials/sidebar/left-sidebar.blade.php, public/js/dtc-app.js |
| public/css/payment-history.css | 8817 | resources/views/transactions/index.blade.php, resources/views/transactions/show.blade.php |
| public/css/pickup-picker.css | 10432 | resources/views/alumni/requests/create.blade.php, resources/views/alumni/requests/index.blade.php, resources/views/student/appointments/create.blade.php |
| public/css/profile.css | 12292 | resources/views/portal/alumni/dashboard.blade.php, resources/views/portal/new-applicant/dashboard.blade.php, resources/views/portal/profile/show.blade.php, resources/views/portal/student/dashboard.blade.php |
| public/css/public-navbar.css | 9576 | resources/views/auth/welcome.blade.php, resources/views/layouts/public.blade.php |
| public/css/registrar-slots.css | 1589 | resources/views/registrar/appointments/slots.blade.php |
| public/css/responsive.css | 1294 | config/adminlte.php |
| public/css/signin-modal.css | 13713 | resources/views/auth/welcome.blade.php, resources/views/layouts/public.blade.php, public/js/signin-modal.js |
| public/css/student-dashboard.css | 8425 | resources/views/portal/student/dashboard.blade.php |
| public/css/utilities.css | 2776 | config/adminlte.php |
| public/css/welcome.css | 46934 | config/adminlte.php, resources/views/auth/welcome.blade.php, resources/views/layouts/public.blade.php |

Filename mentions may be comments (notably appointments-create.js). Browser coverage across applicant types, roles, responsive breakpoints, modals, validation errors, payment states and dark/light themes is required before claiming all unused code has been identified. No standalone .html/.htm files were found under resources/ or public/.
