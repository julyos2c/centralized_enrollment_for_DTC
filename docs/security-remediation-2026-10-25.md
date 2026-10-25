# Audit remediation — 25 October 2026

All six confirmed findings were addressed. The final targeted source review covered injection, authorization, changed-email verification, session enforcement, term integrity, recovery throttling, and interactions between those fixes. No files were staged, committed, or pushed. Earlier academic-record and invitation UI edits were preserved.

## Findings and fixes

| Finding | Root cause | Result |
| --- | --- | --- |
| Stored XSS in search | Stored names and descriptions interpolated into `innerHTML` | Search results use DOM construction and `textContent`. Same-origin HTTP(S) URLs only; classes and icon attributes are constrained. Adjacent suggestions are static and recent searches already use text rendering. |
| Semester mismatch | Subject validation trusted the submitted semester while persistence used configured academic settings | Stale or manipulated semesters are rejected; standard subject validation uses the configured semester. Mixed subject loads remain available through irregular selection. Existing duplicate-enrollment protection remains. |
| Email verification retained after change | Profile/admin updates did not invalidate the old email verification | A User model hook covers existing Eloquent email-update paths. It clears verification and sets a new persisted flag. Normal account access is blocked until a signed, authenticated verification succeeds. A locked recheck prevents verification racing an email update. Initial legacy/activation workflows remain unchanged. |
| Security settings not enforced | Admin persisted values without connecting authentication and password checks | A shared server policy enforces password rules, login limits, and inactivity on subsequent requests. Reads come from the database, not cached configuration or process-local snapshots. Minimum password length is never below eight. Symbol requirements exclude whitespace-only substitutes. |
| Recovery rate limits absent | Public recovery routes lacked request budgets | Laravel named limiters enforce shared IP and email/IP budgets across recovery routes, plus a setup-page IP budget. Existing resend and portal activation limits remain. No global email lockout is added. |
| Broken user creation route | Resource routing registered a nonexistent controller method | Removed only the unused `create` route; modal creation and existing CRUD/reset routes remain. |

## Exact files changed for this remediation

Earlier uncommitted UI changes are not included in this list.

- `app/Support/SecuritySettings.php` — shared password, timeout, and login policy.
- `app/Models/User.php` — centralized email-change verification invalidation.
- `app/Http/Middleware/EnforceAccountSession.php` — server inactivity enforcement and changed-email access restriction.
- `app/Http/Controllers/AuthController.php` — saved login-attempt limit.
- `app/Http/Controllers/Admin/SettingsController.php` — bounded security-setting validation.
- `app/Http/Controllers/Admin/UserController.php` — policy for creation and password resets.
- `app/Http/Controllers/Auth/RequiredPasswordController.php` — policy for required changes.
- `app/Http/Controllers/Auth/ResetPasswordController.php` — policy for recovery resets.
- `app/Http/Controllers/Auth/EmailVerificationController.php` — signed verification, notice, and resend.
- `app/Http/Controllers/PasswordSetupController.php` — policy for applicant activation.
- `app/Http/Controllers/PortalActivationController.php` — policy for imported student/alumni activation.
- `app/Http/Controllers/Portal/ProfileController.php` — password policy and changed-email notice redirect.
- `app/Http/Controllers/Student/EnrollmentController.php` — authoritative semester validation.
- `app/Providers/RouteServiceProvider.php` — named recovery limiters.
- `routes/web.php` — recovery throttles, verification routes, invalid create-route removal.
- `database/migrations/2026_10_25_140000_add_email_verification_required_to_users.php` — additive verification flag, default false.
- `public/js/dtc-app.js` — safe search result rendering.
- `public/js/password-guidance.js` — server-policy guidance and compliant generated passwords.
- `resources/views/components/dtc/password-guidance.blade.php` — escaped policy metadata.
- `resources/views/admin/settings/security.blade.php` — bounds and accurate help text.
- `resources/views/auth/verify-changed-email.blade.php` — email-verification notice.
- `tests/Feature/EmailVerificationMigrationTest.php` — reversible migration rehearsal on the disposable testing database.
- `tests/Feature/AuditFixesTest.php` — 16 feature regressions covering all six findings.
- `tests/js/search-rendering.test.cjs` — literal markup, hostile URLs/attributes, missing images, and fallback behavior.
- `tests/js/password-guidance.test.cjs` — saved policy and generated-symbol coverage.
- `tests/js/application-submission.test.cjs` — LF/CRLF normalization and explicit script-boundary assertions.
- `README.md` — enforcement and deployment instructions.
- `docs/security-remediation-2026-10-25.md` — this report.

## Verification results

- Complete PHP suite: **386 passed, 0 failures; 3,243 assertions**.
- Complete JavaScript suites (`tests/js` and `tests/JavaScript`): **63 passed, 0 failed**.
- Changed PHP files passed syntax checks; edited JavaScript passed syntax checks.
- Blade templates compiled successfully; compiled view cache was then cleared.
- Route listing confirmed verification routes and retained user CRUD routes; no registered controller actions are missing.
- `git diff --check` passed, with only existing line-ending conversion warnings.
- The additive migration ran successfully in the local development database and through the test suite's fresh migrations.
- Static route/asset scanning still identifies unused AdminLTE fallback templates/assets and controller request-parameter references; these are not confirmed live broken links. No PHPStan/Psalm configuration is installed; no claim of a full static-analysis or external dependency-advisory audit is made.

The Windows test failure was caused by searching for LF bytes in a CRLF Blade file. The test now normalizes line endings and explicitly fails if its intended code block cannot be located; its behavioral assertions remain intact.

## Manual and deployment verification still required

Browser test assertions were not completed. A local headless Edge attempt connected to the debugging endpoint but ended before completing the first browser assertion; no browser pass is claimed. Before production release:

1. Search as registrar/admin for a test account containing harmless markup; confirm literal text, normal navigation, and avatar fallback.
2. Submit a valid standard enrollment; repeat with a stale semester and with irregular subjects. Verify the saved semester and correction messages.
3. Change student and staff email addresses. Confirm restricted access, verification mail delivery, signed-link redemption, old/expired/wrong-account rejection, profile correction, and logout. Verification requires signing in as the link's recipient. Repeat an admin-initiated email change.
4. Change password length and symbol settings and try staff creation, profile password changes, reset, applicant activation, and student/alumni invitations.
5. Lower the login limit and timeout in staging. Verify lockout and expiration on later HTTP requests, across tabs and after worker/process restarts. Polling must not extend inactivity.
6. Exercise forgot-password, reset, activation, resend, and rate-limit recovery after the window expires. Confirm generic responses and working mail links under the real HTTPS `APP_URL`.
7. Verify shared cache and session storage across deployment nodes. File stores coordinate only one server; the array driver must not be used in production. Existing session lifetime remains an upper bound on admin inactivity settings.

Apply migrations before routing traffic to the updated code. No new dependencies or environment variables were added. Email transport, the public HTTPS host, persistent distributed counters, and cookie/session behavior require environment-level verification. No live external links or third-party vulnerability database were checked.

Assessment: the six confirmed source-level issues are remediated and automated verification is green. Production readiness remains conditional on the manual/staging checks above.

## Release checklist evidence

The inspected environment is **local**, with `APP_URL=http://127.0.0.1:8000`, file cache and file sessions. Cookies are configured HTTP-only, Secure, and SameSite=Lax. SMTP is configured, but that does not prove successful delivery. No HTTPS staging environment exists, and no production configuration was inspected. This is a single-server development environment. Redis or other infrastructure changes were not introduced.

- [ ] All critical browser security tests pass — browser security scenarios have not been executed.
- [x] Enrollment workflows preserve semester integrity — automated tests cover valid, stale, manipulated, mixed-load, and duplicate submissions. Manual browser confirmation remains required.
- [ ] Email delivery and verification links work over HTTPS — signed-link authorization/expiry is tested locally; actual HTTPS delivery and link redemption remain unverified.
- [ ] Security policies work across sessions and workers — subsequent-request enforcement is covered by automated tests. A separate-process probe confirmed that file sessions and throttle counters persist between two PHP processes on this machine. End-to-end authentication across HTTP workers, multiple nodes, and real browser sessions remains unverified.
- [ ] Shared cache and session configuration verified — available file stores coordinate one server only. The staging/production topology and shared stores are unknown.
- [ ] Production database migration and rollback plans reviewed — source review and a disposable testing-database rehearsal cover the additive migration. Production backup restore, maintenance procedure, and operator execution remain unverified; see the plan below.
- [ ] Previously existing UI changes regression-tested — PHP rendering and JavaScript behavior have automated coverage, but viewport/layout and actual browser interactions remain untested.

### Migration and rollback plan

1. On staging, verify a restorable database backup and record the deployed code revision and migration status. Ensure the configured database is the intended environment; never run `migrate:fresh` against development or production data.
2. Keep production traffic in maintenance mode while deploying the code and running the additive migration. It adds a non-null boolean flag with default false; existing account identities, credentials, and verification timestamps are retained. No historical email-verification flags are inferred.
3. Verify the new column, existing account access, signed email verification, and security policies before reopening traffic. Production still needs correct HTTPS host, mail, cache, and session settings.
4. Prefer a forward fix while leaving the additive column intact. Do not restore the known-vulnerable code as the normal rollback strategy.
5. A schema reversal drops the flag and loses pending verification state. Before considering it, keep traffic stopped, inventory accounts with `email_verification_required=1`, and retain a backup of that state. If any accounts need verification, do not drop the column until a reviewed recovery plan preserves those restrictions. Removing the flag alone is not a safe security rollback.
6. Only with compatible code and an approved recovery plan should an operator reverse this specific migration. Do not roll back an entire migration batch blindly. Restore code and database consistently if snapshot recovery is required, accounting for writes after the backup. Rehearse this on staging first.

`tests/Feature/EmailVerificationMigrationTest.php` exercises the target migration's `down()` and `up()` on the disposable test database and checks identity/password/status/verification timestamps survive. It cannot prove production backup recoverability or deployment availability characteristics.
