# Session security hardening - 2026-10-22

## Implemented controls

| Root cause | Fix |
|---|---|
| Account status was checked only at login. | Web and authenticated API requests check the current database account state. Non-active accounts are rejected; browser sessions are invalidated. JSON requests receive 403 for blocked accounts or 401 for revoked sessions. |
| Password resets did not revoke existing sessions or remember-me credentials. | Password/status model updates rotate a random server-side session token and remember token, and delete the affected user's Sanctum tokens. Login stamps the current token into the session; old sessions fail on their next request. No session-driver-specific enumeration or deletion is required. Other users' sessions remain valid. |
| Temporary-password copy promised an unenforced restriction. | Administrative resets set must_change_password. Middleware allows only the authenticated password-change and logout routes until successful change. Password change requires the current temporary password, confirmation, at least eight characters and a different password. The record is locked and the password rechecked before clearing the flag. |
| Failed profile password checks flashed all submitted input. | Explicitly exclude current password, new password and confirmation from flashed input. Successful self-service changes regenerate the current session and stamp its new token; other sessions are revoked. |
| Notification mutations could treat HTTP failure as success. | Shared checked fetch rejects non-success responses before UI mutations, with explicit 401/403/419 messages and catches. Polling stops on authentication/CSRF failures. |
| AJAX filters/loaders did not distinguish authentication from empty results. | Shared response checking rejects failed requests and login/password-change redirects. Filters stop their loading state and show errors. Search no longer labels failed requests as empty results. jQuery modal loaders report HTTP failure explicitly. No write retry is performed. |
| Draft storage was cleared before a submission response. | Keep localStorage pending. Snapshot the draft in sessionStorage on final confirmation. Only a server-flashed successful-submission page can clear the matching snapshot. A newer draft from another tab is retained. |
| Avatar URL regression on LAN/tunnel/mobile origins. | Public local-storage URLs now default to /storage rather than APP_URL=http://localhost. PUBLIC_STORAGE_URL remains an optional CDN/storage URL override. Uploaded avatar files were verified present; configuration/view caches were cleared. |

## Migration and rollout

Added and applied locally: `database/migrations/2026_10_22_160000_add_account_session_security_to_users.php`.

Adds nullable `users.auth_session_token` and default-false `users.must_change_password`. Existing users have a null marker and are not forced out solely by deployment. Subsequent password/status changes rotate it; legacy sessions with no matching marker then fail. The marker is hidden from model serialization and is never accepted from profile input.

Run the migration on other deployments before serving this code. No session lifetime extension, CSRF exclusion, permission change or dependency was introduced. GET logout remains non-mutating; stale logout recovery retains CSRF checks. Administrator self-reset legitimately revokes that administrator's own session; resetting another user does not.

Revocation is enforced on the next request; already-running work is not retrospectively cancelled. Direct database updates that bypass Eloquent events also bypass token rotation; supported administrative endpoints use model updates. Current account status is still checked from the database on every request.

## Exact implementation files

Server:
- `app/Http/Middleware/EnforceAccountSession.php`
- `app/Http/Kernel.php`
- `app/Models/User.php`
- `app/Providers/EventServiceProvider.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/Auth/RequiredPasswordController.php`
- `app/Http/Controllers/Auth/ResetPasswordController.php`
- `app/Http/Controllers/Portal/ProfileController.php`
- `app/Http/Controllers/Public/ApplicationController.php`
- `routes/web.php`
- `routes/api.php`
- `config/adminlte.php`
- `config/filesystems.php`
- `database/migrations/2026_10_22_160000_add_account_session_security_to_users.php`

Frontend:
- `public/js/session-feedback.js`
- `public/js/dtc-feedback.js`
- `public/js/dtc-app.js`
- `public/js/profile.js`
- `public/js/application-wizard.js`
- `public/js/cashier-notification-polling.js`
- `resources/views/layouts/public.blade.php`
- `resources/views/auth/required-password.blade.php`
- `resources/views/notifications/index.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/admin/programs/index.blade.php`
- `resources/views/registrar/applications/index.blade.php`
- `resources/views/registrar/appointments/schedule.blade.php`
- `resources/views/registrar/enrollments/_review-modal.blade.php`
- `resources/views/cashier/payments/_payment-modal.blade.php`
- `resources/views/cashier/payments/_receipt-modal.blade.php`
- `resources/views/student/enrollment/create.blade.php`
- `resources/views/public/application/success.blade.php`

Tests:
- `tests/Feature/AccountSessionSecurityTest.php`
- `tests/js/application-submission.test.cjs`
- `tests/js/session-feedback.test.cjs`

## Validation

- Full PHPUnit suite: 296 tests, 2,080 assertions passed (`storage/logs/phpunit-security-final.txt`).
- After the final profile-input fix, focused security/profile tests: 6 tests, 57 assertions passed, including the additional password-flash regression test.
- Node suite: 11 tests passed. Includes rejection of 401/403/419/500 before success continuations, no automatic retry, redirected login HTML rejection, failed submission draft retention and success-only matching-draft deletion. These use simulated DOM/storage/network responses.
- Explicit backend tests cover locking an existing session, unlock not reviving a revoked session, targeted administrative reset, unrelated token preservation, old remember-cookie rejection, revoked API token rejection, mandatory-password bypass prevention, invalid/current password handling and current-session continuity after self-service password change.
- Avatar/dashboard regression tests: 17 passed after the same-origin URL fix.
- Blade compilation, new route loading, migration status and modified JavaScript syntax checks passed.

## Manual verification still required

Use separate real browser sessions to verify locking, administrative resets, remembered sign-in, password-change completion, back-button behavior, and an idle-page logout. Exercise notification read/delete, filters and modal loaders after expiry on desktop/mobile. Verify actual LAN/tunnel avatar HTTP responses. Simulate real network loss during submission, and check storage/privacy/browser restrictions and cross-tab draft behavior. Automated simulated failures do not prove complete browser/network workflows.

No automatic replay of POST/PUT/PATCH/DELETE requests is added. Browser users must explicitly reload or sign in when prompted; unsaved input and files should be preserved/copied before navigating. Password-reset sessions must authenticate with the new temporary password; the change requirement is cleared only by a successful password change (including the existing broker reset flow).
