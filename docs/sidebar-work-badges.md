# Sidebar pending-work badges

Badges represent shared staff work queues, independently of unread notifications or the current list filters.

| Menu | Count rule |
|---|---|
| Applications | Submitted applications awaiting initial review |
| Review Enrollments | Pending enrollments |
| Appointment Requests | Pending appointments |
| Document Requests | Submitted requests awaiting processing |
| Process Payments | Approved, unpaid enrollments whose latest payment is not verified; count enrollments once, not payment attempts |
| Document Fees | Non-cancelled, non-released requests with unpaid or pending-verification payment status |

Admin receives all six counts, registrar receives the four registrar counts, and cashier receives the two payment counts. Portal roles receive no staff queue counts. Badges are rendered only beside matching authorized menus. Zero is hidden, display caps at 99+, and accessible labels retain the full count. Historical, completed and read notifications do not affect work counts.

`app/Services/SidebarWorkCounts.php` supplies both initial menu rendering and the existing notification poll response. Counts are memoized only within the HTTP request to avoid repeating queries for every Blade partial. No extra poller, database schema, persistent cache or client-authoritative count state was added. Changes become visible on the next ordinary page load or successful approximately five-second poll; hidden tabs update when visible again. Existing polling inactivity/security controls remain unchanged.

Implementation files: the new service; `ViewServiceProvider.php`; `NotificationController.php`; sidebar `menu-item-link.blade.php`; `dtc-theme.css`; and `cashier-notification-polling.js`. Regression coverage is in `SidebarWorkCountsTest.php` and `notification-polling.test.cjs`.

Validation: complete PHP suite **334 tests / 2,560 assertions passed**, complete JavaScript suite **28 passed**, and Blade compilation, notification route registration, polling syntax and working-tree whitespace checks passed. Tests cover role scope, queue transitions, multiple payment attempts, read-state independence, zero hiding, 99+ display and accessibility counts. Browser/mobile visual verification remains manual. No staging, commit or push was performed.
