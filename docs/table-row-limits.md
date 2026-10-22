# Table row limits

Account data tables now offer 5, 10, 15, 20, 25 and 50 rows per page. Existing server defaults are retained until a limit is selected.

- Server pagination uses `App\Support\TablePagination`; unsupported input falls back to the existing default. Transaction history retains its existing validated 1–50 range for compatibility.
- The shared `partials.table-rows` control moves into an existing GET filter form when available. Otherwise it stays above the table. Changes preserve filters and reset standard/named page parameters, while preserving `per_page` itself.
- Collection tables use local paging with row counts and Previous/Next controls. Local limits are stored in the current URL. No new role permissions or data scopes are introduced.
- AJAX-replaced tables are initialized automatically. User management's custom AJAX filter builder preserves the selected limit.

Covered tables include users, programs/subjects, audit logs, registrar applications/enrollments/appointments/documents, closures, import batches, academic records/history, cashier queues/dashboard, registrar dashboard, student dashboard subjects and shared transaction history for all roles.

Excluded deliberately: email layouts, receipts, certificates/COR, print reports, import validation previews, and bulk-edit/selection tables. These must remain complete; this feature does not limit exported/printed data. Card-only lists are not converted into tables.

Validation: full PHPUnit suite passed (285 tests, 1,994 assertions); six Node tests passed, including local paging and server filter/page resets. Blade compilation and route loading passed. Browser verification is still needed for filter positioning, mobile layouts, AJAX updates, multiple-table pages, and local filter/print interactions. No browser rendering or Vite build was performed.
