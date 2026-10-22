# Frontend cleanup results - 2026-10-22

## Bug fixed

The wizard referenced a nonexistent `acknowledgement` input. The actual form checkbox is `ack_enrollment`, which the controller validates as required and accepted. Removed the obsolete DOM lookups and writes without introducing another input or changing backend validation. Kept consent feedback, duplicate-submit guard and the shared confirmation flow.

## Deleted files

- `public/css/appointments-create.css`
- `public/js/appointments-create.js`
- `resources/views/admin/users/create.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/partials/pagination-summary.blade.php`
- `resources/views/partials/ui-polish.blade.php`
- `resources/views/components/dtc/file-upload.blade.php`
- `resources/views/components/dtc/loading-button.blade.php`

Rechecked routes, controllers, Blade includes/components, script references, configuration and generated markup before deletion. The only external reference to appointments-create.js was a comment documenting its replacement by student-booking.js. No component invocation was found for either deleted component; similar classes on active upload markup are not component invocations.

## Removed CSS and JavaScript

- In application-wizard.css, removed the obsolete location bottom-sheet component: `.loc-picker-*`, `.loc-tab*`, its component media queries and `locBackdropIn`/`locDialogIn` animations. Kept `locSpin` and other preexisting location rules outside that component because they were outside the confirmed removal scope.
- Removed the five `.opt` rules, including the combined honorific/suffix rule.
- In application-wizard.js, removed `confirmSubmit` and `confirmCancel` lookups and their guarded event listeners. Kept `dtcConfirmModal` and `window.dtcConfirm.open`.

## Intentionally preserved

Dynamic status/color classes, vendor CSS/JS, AdminLTE overrides, shared confirmation components, active upload handlers/styles, student-booking.js, document-request.js, Vite entry points, empty but configured dtc-dark-global.css, dormant guidance views and their components, errors/layout.blade.php, auth/_auth-layout.css, legacy location code-field handling, and index.blade.php.bak.

No backend, database or route changes. The users resource still declares create/edit routes without matching controller methods; this preexisting route issue is separate from unused views and was not changed here. The deleted user-create view was not rendered by a controller method.

## Automated validation

- Initial wizard regression tests after the acknowledgement fix: 2 passed.
- Intermediate Laravel education/location tests after CSS/listener cleanup: 17 passed, 518 assertions.
- Final complete PHPUnit suite: **280 passed, 1,931 assertions**. Output: `storage/logs/phpunit-cleanup.txt`.
- Final Node tests: **3 passed** across application-submission.test.cjs and application-education.test.cjs. They exercise consent changes without the missing input, native submit blocking, duplicate submission guard, shared confirmation submission, applicant-type switching, field enablement and value retention with a simulated DOM.
- JavaScript syntax check passed for application-wizard.js.
- Blade view compilation and route-list generation passed.
- CSS delimiter-balance check and git diff whitespace check passed. These are structural checks, not browser rendering validation.
- No live source consumers of deleted assets/views/components remained; student-booking.js retains a historical replacement comment.
- Vite build was not run: node_modules is absent. Changed assets are served directly from public/; no Vite entry point changed.

## Manual verification still required

Automated coverage is not proof of complete interactive workflows. Verify in a real browser:

1. Application wizard navigation, required-field feedback, consent correction, confirmation cancel/confirm and successful submission.
2. Appointment booking, management, payment panels and receipt/file uploads, including previews and validation errors.
3. Admin dashboard and modal-based user creation.
4. Sign-in, sign-out and role-specific navigation.
5. Desktop/mobile layout, keyboard focus, dark/light themes and shared modal behavior.

The source-based removals should not alter rendered UI because the deleted templates/assets/selectors had no active consumer. Visual equivalence has not been browser-verified. New JavaScript tests use a simulated DOM, not a browser.
