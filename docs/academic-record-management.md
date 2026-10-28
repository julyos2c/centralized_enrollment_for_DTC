# Academic record management and imports

The Registrar and Admin role group can manage academic-only student/alumni records at `/registrar/students` and upload CSV files at `/registrar/imports`. Academic records do not create, activate, or link portal accounts. Existing linked profiles remain linked; existing records are matched by student number and skipped rather than overwritten.

## Import format and limits

The import center provides header-only CSV templates. Student rows use `Student Number, First Name, Middle Name, Last Name, Suffix, Email, Program, Year Level, School Year`. Alumni rows use `Student Number, First Name, Middle Name, Last Name, Suffix, Email, Program Completed, Graduation Year, Graduation Date`. Student ID no. and first/last names are required. Program must exactly identify one existing program when provided. School year is `YYYY-YYYY`; graduation date is `YYYY-MM-DD`.

Uploads default to 5 MB and 1,000 data rows and are kept on the private local disk under `storage/app/private/academic-imports`. Set `ACADEMIC_IMPORT_MAX_KB`, `ACADEMIC_IMPORT_MAX_ROWS`, and `ACADEMIC_IMPORT_PREVIEW_TTL_HOURS` in the runtime environment to change those limits. Preview data defaults to 24 hours. Configure Laravel's scheduler on the host to run `php artisan schedule:run` every minute so `academic-imports:cleanup` removes expired upload and report files. A confirmed import keeps only a row-number/error report; it deletes the source file. The report route remains behind the Registrar/Admin middleware.

XLSX is deliberately rejected because the installed Composer dependencies do not include a spreadsheet reader compatible with this project. CSV values are parsed as text; formulas/macros are not executed. No additional package or framework upgrade was introduced.

## Schema and rollback

Migration `2026_10_20_000002_create_academic_record_management_tables` makes `student_profiles.user_id` nullable and changes its foreign key to `ON DELETE SET NULL`, allowing historical academic records to survive account deletion. It adds optional identity/contact/academic fields and indexes for academic status and graduation year. Student ID no. remains uniquely constrained. `academic_import_batches` stores references, counts, source hashes, private file paths, status, timestamps, and the initiating account; it does not store raw row values.

The migration's `down()` refuses to run while import history, account-free records, or populated new academic fields exist. Archive required import history and academic-only data before intentionally removing this feature. It does not delete or transform payments, applications, enrollment transactions, certificate requests, or portal accounts.
