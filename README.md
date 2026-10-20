# DTC Nexus

DTC Nexus is a Laravel 9 enrollment management system for Danao Technological College. It supports public applications, enrollment review, verified payment processing, student and alumni self-service, appointments, notifications, reporting, and administrator settings.

## Modules

- **Applicants:** submit an application and documents, check its status, respond to revision requests, and activate an account after approval.
- **Students:** submit or resubmit enrollment, select subjects, submit GCash proof, view payment status and a Certificate of Registration (COR), manage profile details, and book appointments.
- **Alumni:** request school documents and track requests and appointments.
- **Registrar:** review applications and enrollments, manage appointment slots, document requests, and student progression.
- **Cashier:** record walk-in payments and verify or reject submitted GCash payments. Only verified/recorded payment marks an enrollment paid.
- **Admin:** manage users, programs, subjects, settings, reports, audit logs, and database backup files.

## Requirements

- PHP **8.0.2 or later**, with the extensions required by Laravel and the installed Composer packages (including PDO MySQL, OpenSSL, Mbstring, tokenizer, XML, Ctype, JSON, and fileinfo).
- Composer 2.x.
- Node.js 16 or later and npm (the project uses Vite 4).
- MySQL 8 or MariaDB 10.4 or later, with a database and user created for this application. Migrations use MySQL-compatible schema features.
- A configured mail transport for activation and application-status emails. Mailpit is convenient for local development.

Run `composer check-platform-reqs` after installing Composer dependencies to check the PHP platform. The exact tested version combination depends on the host; this README does not certify a production environment.

## Local setup

### Windows (XAMPP or Laragon)

1. Install PHP, Composer, Node.js/npm, and MySQL or MariaDB. Ensure the PHP CLI selected in your terminal is the same PHP installation used by the web server.
2. Start the database service and create an empty database, for example `dtc_ems`, using `utf8mb4`.
3. Open PowerShell in the project directory and install dependencies:

   ```powershell
   composer install
   npm install
   Copy-Item .env.example .env
   ```

4. Edit `.env`: set `APP_NAME`, `APP_URL` (for example `http://127.0.0.1:8000`), database connection values, and local mail settings. Never copy real credentials into source control.
5. Generate the application key and build the schema and reference data:

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   ```

   In the `local` environment this also adds a sample (unapproved) curriculum. To get a local administrator, set `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD` in your local `.env` before seeding; no account is created otherwise, and an existing account with that email is never modified. For production, provision administrators through a controlled, audited process.

6. Build frontend assets and start the local server:

   ```powershell
   npm run build
   php artisan serve
   ```

   Visit the `APP_URL` shown by `artisan serve`. During frontend development, use `npm run dev` in a second terminal instead of the production build.

### Other supported environments

On Linux, macOS, or a container, install the same PHP extensions and database service, then run the Composer/npm, `.env`, key, migration, and build commands above from the project root. Configure the web server document root to `public/`, not the repository root. Configure the process manager/web server to use the intended PHP version. Container orchestration files are not included in this repository, so container-specific services, volumes, and secrets must be supplied by the deployment environment.

### Environment variables

The committed `.env.example` is a local template. At minimum configure:

| Setting | Purpose |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY`, `APP_TRUSTED_HOSTS` | Environment, error display, canonical URL, encryption key, and comma-separated exact hostnames accepted by the web app. Generate a unique key; keep it secret and stable for the life of the installation. The APP_URL hostname is trusted automatically; add every production alias to APP_TRUSTED_HOSTS. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL/MariaDB connection. Use a dedicated least-privilege database user outside local development. |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Outbound email used by application review and account activation. Configure and verify a real mail provider in production. |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE` | Session storage and cookie lifetime/security. Local HTTP can use false; production HTTPS must use `SESSION_SECURE_COOKIE=true`. `http_only` is enabled and SameSite is `lax` in `config/session.php`. |
| `CACHE_DRIVER`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, `LOG_CHANNEL`, `LOG_LEVEL` | Cache, queue, uploaded-file storage, and logs. The shipped defaults are suitable only for simple local use. |
| `LOGIN_RATE_LIMIT_PER_IDENTITY`, `LOGIN_RATE_LIMIT_PER_IP`, `LOGIN_RATE_LIMIT_DECAY_SECONDS`, `LOGIN_RATE_LIMIT_PER_EMAIL`, `LOGIN_RATE_LIMIT_PER_EMAIL_DECAY_SECONDS` | Optional login throttling overrides. Defaults are 5 attempts per normalized email+IP and 300 attempts per IP per 5 minutes to allow shared campus networks, plus 20 attempts per email from any IP per 15 minutes (the client IP can be spoofed on direct-to-origin requests). Bot protection is provided by Cloudflare at the edge; there is no in-app CAPTCHA. Use a shared production cache backend so limits apply across application workers. |
| `APPLICATION_SUBMISSION_LIMIT_PER_IP` | Public `POST /apply` attempt limit per source IP per hour (default 5; failed validation requests count too). Refreshing the form does not reset this limit. Applicants behind a shared campus/NAT address share the allowance; distributed requests from different IPs are not covered by this per-IP control. Use a shared production cache backend across application workers. |

`APP_KEY` must not be regenerated on an existing installation: doing so can invalidate encrypted data and sessions. Do not commit `.env`, credentials, uploaded files, or generated backups.

## Database and seed data

Create an empty database before running migrations. For local development:

```sh
php artisan migrate --seed
```

For a deployed environment, use `php artisan migrate --force` only after taking and checking a backup. `DatabaseSeeder` is production-safe: every seeder it runs is idempotent and insert-only, never truncates, deletes, or overwrites records, and never creates users or passwords.

| Seeder | Kind | Writes |
|---|---|---|
| `RolePermissionSeeder` (alias `RoleSeeder`) | Mandatory reference data | Missing `roles` only (the six application roles). No permissions: authorization is role-based. |
| `SystemSettingsSeeder` | Mandatory reference data | Missing operational keys in `settings` only; never changes an existing value. Fees, GCash, term, and institution details are not seeded. |
| `AcademicConfigurationSeeder` | Optional, approved data only | Missing `programs`, `course_subjects`, and an unset term, from `database/seeders/data/academic-configuration.json` (see the README there). No file, no changes. |
| `DevelopmentSeeder`, `SubjectSeeder`, `ProgramSeeder`, `DemoDataSeeder` | Sample data | Refuse to run outside `local`/`testing`. |

Run in production with `php artisan db:seed --force` after a backup. Avoid `migrate:fresh`, `migrate:refresh`, or `db:wipe` on any database that must be preserved.

## Tests

The PHPUnit configuration uses a real MySQL-compatible test database named `dtc_testing`, because some migrations alter foreign keys/columns in ways the current SQLite test setup does not support. Create a separate **empty disposable database** and configure its host, port, username, and password in `.env.testing` or the `<env>` entries in `phpunit.xml`. Never point the test configuration at a production or development database: `RefreshDatabase` migrates and resets test schema state.

Run the suite with:

```sh
php artisan test
```

Or run selected coverage with `vendor/bin/phpunit tests/Feature/StudentGcashPaymentTest.php`. Tests use an array mailer, synchronous queue, and array session. Database connection values in `phpunit.xml` override environment values unless the configuration is changed.

## Accounts and activation

Applicants use the public **Apply** workflow; there is no public student self-registration route. The Registrar reviews an application. An approved application creates/links an account and sends a one-time password setup link through the configured email transport. The applicant sets a password at that link, which also marks the email verified. Expired links can be renewed through the activation request/resend flow; responses are intentionally generic. Students and alumni sign in at `/login`. Staff accounts and role assignments are managed by authorized administrators. Keep mail delivery working so approved applicants can activate their accounts.

## Enrollment and payment rules

Enrollment records use `pending`, `approved`, or `rejected`. Rejected enrollments retain Registrar feedback in `remarks`; students may submit another enrollment after rejection. Approval alone does **not** complete enrollment. A GCash payment proof remains pending until Cashier verification; only successful verification (or a Cashier-recorded walk-in payment) sets `is_paid` and issues payment receipt information. Do not manually mark an enrollment paid to bypass that process.

## Production deployment checklist

These are deployment requirements, not steps verified against a live server:

- Deploy a reviewed release and install Composer dependencies (`composer install --no-dev --optimize-autoloader`). This repository has no npm lockfile, so frontend builds currently use `npm install` followed by `npm run build`; add and review a lockfile before relying on reproducible `npm ci` builds.
- Set `APP_ENV=production`, `APP_DEBUG=false`, the correct HTTPS `APP_URL`, and a protected, persistent `APP_KEY`. Do not expose `.env`, source files, logs, SQL dumps, or private uploads through the web root.
- Set `APP_TRUSTED_HOSTS` to a comma-separated list of exact production hostnames (including any aliases). `TrustHosts` also accepts the host in `APP_URL`; it rejects other Host headers. Keep `APP_URL` set to the canonical HTTPS hostname.
- Terminate HTTPS correctly and set `SESSION_SECURE_COOKIE=true`. Use a suitable production session/cache backend and restrict cookie domain/path as needed. Keep HTTP-only and SameSite protections enabled.
- Serve the site through Cloudflare and configure its bot protection (WAF/rate-limiting rules for `POST /login` and `POST /apply`). The application has no CAPTCHA; its login and application rate limits are the server-side backstop. Test a valid login and an invalid Host header in staging.
- Use a dedicated least-privilege database account, strong unique credentials, network restrictions, and regular tested backups.
- Configure real outbound email and verify application/activation delivery. Configure a durable queue worker only if changing `QUEUE_CONNECTION` from `sync`; supervise and monitor that worker.
- Set writable ownership/permissions only for Laravel's `storage/` and `bootstrap/cache/` as required by the web/PHP process. Do not make the whole project writable or world-writable.
- Configure log retention/monitoring and alert on mail, database, storage, and backup failures.
- From the release directory, after environment changes run `php artisan config:clear`, verify the deployment environment values, and then run `php artisan config:cache`; restart long-running PHP workers so they load the new configuration. Run database migrations with `php artisan migrate --force` after backup. Then run `php artisan route:cache` and `php artisan view:cache`. Confirm custom routes can be cached before relying on route caching in the target release.
- Verify Host filtering in staging: requests with each listed hostname and the `APP_URL` host should work; a request sent with an unrelated `Host` header should be rejected by the application/web stack.
- Verify the web root points to `public/`, built assets exist under `public/build`, and all application URLs use HTTPS.
- Provision administrator accounts securely; do not use the seeded local default administrator in production.
- Test login, role access, application email, enrollment, Cashier verification, COR access, private upload access, and restore procedure in a staging environment before release.

## Backups and recovery

The Admin backup page creates SQL files under `storage/app/backups/` and provides authenticated downloads. The current web restore endpoint intentionally rejects uploaded files; it does not import SQL. The built-in dump implementation and storage location are application-specific and should not be treated as a complete disaster-recovery service without validating them for the target database/version and data size. Keep independent encrypted/off-site backups and test restoration.

### Protecting backup data

- Restrict the Admin backup page and downloaded SQL files to trusted administrators. SQL backups contain personal data and must be encrypted at rest and in transit, access-controlled, and retained according to institutional policy.
- Back up the database and private uploads in `storage/app/` (including `storage/app/payment-proofs`, application documents, and generated backups) using an approved server-side backup tool. Back up public uploads in `storage/app/public` as well. The repository does not configure an external object store or automated file-backup service.
- Do not restore a database by opening or executing arbitrary uploaded SQL/text files. Treat any incoming dump as untrusted until its source and integrity are verified by the system owner.

### Offline restore outline

1. Obtain the dump from the approved protected backup store; verify its recorded source, date, integrity/checksum, and authorization with the system owner. Do not use a file uploaded through the application.
2. Provision an isolated compatible MySQL/MariaDB instance and an empty target database. Restrict access and preserve a separate backup of the target before replacing any data.
3. Have an authorized database operator inspect and restore the verified dump using the database vendor's approved administrative tooling, with credentials supplied securely (not embedded in a command saved to shell history). The web application does not provide a restore command or support arbitrary SQL imports.
4. Restore matching private/public uploaded files from the corresponding trusted backup. Restore the matching protected `APP_KEY` and environment configuration; do not expose these files publicly.
5. Validate migrations/schema, representative records and relationships, file availability/permissions, login and role access, mail configuration, payment/enrollment views, and logs in the isolated environment. Compare counts/checksums with the backup where practical.
6. Only after owner approval, point the application to the recovered environment, rebuild Laravel caches, and monitor it. Keep the pre-restore copy until the recovery is accepted.

Test this procedure in staging; the repository does not include a dedicated database restore utility or an automated uploaded-file backup system.

## Troubleshooting

- **Composer reports missing extensions/platform requirements:** enable the required extension in the active PHP CLI configuration and run `composer check-platform-reqs`. XAMPP's web PHP and terminal PHP can use different `php.ini` files.
- **Database connection or migration fails:** confirm the DB service is running, the database exists, credentials/port are correct, and the configured account can create/alter tables. Check `php artisan migrate:status`; do not try destructive `fresh` commands on existing data.
- **Tests connect to the wrong database:** inspect `.env.testing` and the DB values in `phpunit.xml`; create and use only the dedicated disposable `dtc_testing` database.
- **Activation/application email is missing:** verify the configured SMTP/provider host, port, encryption, credentials, sender authorization, and `APP_URL`; inspect `storage/logs/laravel.log` and provider delivery/bounce logs. Local `.env.example` points to Mailpit by default, which must be running to capture local mail.
- **Login/session loops or sessions do not persist:** check `APP_KEY`, session backend availability, writable `storage/framework/sessions` (for file sessions), correct HTTPS/proxy configuration, and cookie secure/domain settings. A secure cookie will not be sent over plain HTTP.
- **Vite manifest or missing CSS/JS:** install npm dependencies and run `npm run build`; use `npm run dev` during local development. Ensure `public/build/manifest.json` was deployed with the release.
- **Uploads or backups fail:** ensure the PHP process can write the required `storage/` subdirectories, check free disk space and PHP upload limits, and confirm private files are not being placed under a public web directory.
- **Stale pages/routes/config after deployment:** rebuild the appropriate configuration, route, and view caches for the current release and restart long-running PHP/queue processes as required by the host.

## Useful commands

```sh
php artisan route:list
php artisan migrate:status
php artisan test
npm run build
```

## Appointment schedule generation

Configure operating weekdays, Registrar's office hours, breaks, slot duration, capacity, and booking window from **Registrar → Appointments → Office Schedule & Closures**. Class suspensions do not close appointment availability; only a confirmed office closure does. The generator does not create slots until a valid schedule is saved.

After deploying this feature, back up the database and apply the additive migration:

```sh
php artisan migrate --force
```

Generate slots immediately or inspect the configured scheduler:

```sh
php artisan appointments:generate-slots
php artisan schedule:list
```

The Laravel scheduler generates upcoming slots daily at 00:10 in `Asia/Manila`. Production must invoke Laravel's scheduler every minute, for example:

```cron
* * * * * cd /path/to/dtc-ems && php artisan schedule:run >> /dev/null 2>&1
```

On Windows local development, run `php artisan schedule:work` in a dedicated terminal. The generator is safe to rerun and leaves existing/manual slots and appointment history intact. Review pending appointments after recording a closure; affected students are notified, and their bookings/payment records are not automatically cancelled or refunded.
