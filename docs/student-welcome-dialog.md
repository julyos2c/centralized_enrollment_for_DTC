# Student welcome dialog

The actual applicant-to-student role swaps are in `Cashier\PaymentController::store()` (walk-in payment) and `verify()` (GCash verification). Registrar approval does not promote an account. Only these existing `new_applicant` to `student` swaps record the welcome; existing students and administrative role edits are not backfilled.

## Persistence and delivery

Migration `2026_10_24_000001_create_student_welcomes_table.php` creates one event per user with promotion/acknowledgement timestamps and a short display lease. Its `down()` drops only this table. Run this migration before deploying the updated polling controller; it has already been applied to the local application database.

`StudentWelcome::record()` runs in the same payment transaction as the role swap and creates an event-keyed permanent notification through `NotificationService`. Transaction failure rolls back both records. Neither normal polling nor opening the dialog acknowledges the welcome.

The existing authenticated `GET /notifications/poll` returns `student_welcome`, scoped to the current user and current student role. Optional `welcome_token` renews only the matching display lease. It does not extend the existing login inactivity deadline. No second poller is added.

New route: `POST /notifications/student-welcome` (`notifications.student-welcome`), with CSRF protection, authentication, throttling, a UUID token, and `action` of `claim`, `release`, or `acknowledge`. A database row lock makes claims exclusive. Another tab can recover an abandoned lease after 30 seconds. Claims cannot acknowledge another user's event.

The single Continue button, Escape and backdrop dismissal acknowledge on the server before closing. Only an applicant-only application page or a dashboard rendered while the user was still a new applicant navigates to `portal.dashboard` afterward. Other pages, including payment and profile pages and an already-current student dashboard, remain in place. Network failure leaves the dialog open with retry feedback. BroadcastChannel and storage events synchronize acknowledgement across tabs without redirecting other tabs. Hidden tabs release their display claim; the existing visible-page poll can rediscover an unacknowledged event. A lost claim closes the old display after its next poll.

## Presentation

The dialog has a decorative blue check emblem, Poppins typography, a congratulatory heading and one Continue button. It uses an accessible dialog role, initial focus, keyboard focus containment, Escape handling, and focus restoration. The DOM moves outside the navbar stacking context so its backdrop covers the page.

The dialog follows the existing `dtc-dark` classes on HTML/body and reuses the portal surface, secondary-text, and primary-color variables where they match the design. Dark-only values live in CSS; confetti colors also live in CSS. It needs no separate theme detector or theme event handler. The existing shared theme resolver and three pre-paint scripts now honor saved light/dark choices and otherwise use the system preference (the previous shared fallback was always light).

Theme verification set the existing storage key to dark/light in JavaScript tests, ran the actual shared theme functions, and confirmed both root classes update while the welcome stays open. All three pre-paint scripts were tested with saved and system-default combinations. WCAG luminance checks verified every normal/hover text-button palette against a 4.5:1 minimum. The latest theme changes passed 37 JavaScript tests and five welcome feature tests, PHP/JS syntax checks, and Blade compilation. A real-browser preview was attempted with a local HTML fixture and saved dark choice, but Edge headless failed with GPU/process errors; visual appearance has not been verified.

Existing payment/confirmation modals delay the welcome until a later poll. The underlying page's form values are untouched and there is no automatic navigation. Confetti uses 28 desktop/16 mobile pieces, the requested four colors, no sound, pointer events disabled, and removal after 3.5 seconds. Reduced-motion preference disables it.

## Changed files

- `app/Services/StudentWelcome.php`
- `app/Http/Controllers/Cashier/PaymentController.php`
- `app/Http/Controllers/NotificationController.php`
- `database/migrations/2026_10_24_000001_create_student_welcomes_table.php`
- `routes/web.php`
- `resources/views/components/dtc/student-welcome.blade.php`
- `resources/views/partials/navbar.blade.php`
- `public/css/student-welcome.css`
- `public/js/student-welcome.js`
- `public/js/cashier-notification-polling.js`
- `tests/Feature/StudentWelcomeTest.php`
- `tests/js/student-welcome.test.cjs`
- `docs/student-welcome-dialog.md`

## Validation

Feature tests cover both real promotion paths, no guessed/backfilled promotion, event and notification deduplication, recipient isolation, exclusive/expiring claims, acknowledgement permanence, transaction rollback, and lease heartbeats preserving login inactivity expiry. JavaScript tests cover focus, Escape, both buttons, failure handling, cross-tab synchronization with storage fallback, modal deferral, hidden-tab release, confetti counts/removal, and reduced motion.

The integrated working tree passed 339 PHPUnit tests (2,604 assertions) and 34 JavaScript tests. Changed PHP/JS files passed syntax checks; Blade compilation and route inspection passed; the Git conflict-marker scan found no markers. `php artisan test` was attempted but Symfony Process reported that the existing working directory did not exist in its subprocess environment; invoking the installed PHPUnit runner directly succeeded. No dependencies were installed.

Browser appearance and animation have not been visually verified. Previously uncommitted changes are preserved separately from the feature commit. The shared files are staged selectively, including the minimal generic polling support needed by this feature.
