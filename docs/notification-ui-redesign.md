# Notification UI redesign

Later security/reliability hardening is documented in `notification-security-fixes.md`: server-side inactivity enforcement, pending hidden alerts, bounded request timeouts/retries, and authoritative cross-tab dropdown snapshots.

Implemented the dropdown and full notification page using shared `notifications.css` and `notifications.js` assets.

- Dropdown: latest five updates, event-category icons, two-line message previews, subtle unread highlighting, explicit read/unread controls, working Mark all as read and always-visible View all link. Mobile panel width is constrained to the viewport.
- Page: server-side search, All/Unread/Read tabs, notification-type and rows-per-page filters, pagination, explicit destination/read/delete actions, empty-result states and timestamp details. Counts and queries remain scoped to the signed-in user.
- New UI links use the nonmutating `notifications.open` endpoint. Existing `notifications.read` links retain their legacy mark-and-redirect behavior for compatibility. New state changes use authenticated, CSRF-protected POST requests with owner checks.
- Destination resolution checks local GET routes, role restrictions, implicit model existence and portal ownership. Missing or unavailable destinations fall back to the notification page with an explanatory message. Destination controllers retain final authorization responsibility.
- Client mutations disable their controls while pending and update badges only after a successful server response. Failures preserve state and use existing session/error feedback. List reloads preserve search/filter query parameters, with recovery from an out-of-range page after deletion. Cashier polling uses the new controls and ignores stale responses overlapping a read-state write.
- Keyboard focus outlines, explicit button labels, user-action announcements and reduced-motion styles are included. Notification history is not automatically removed. Existing explicit deletion remains available with confirmation. No schema change was required.

Validation: full PHP suite **322 tests, 2,345 assertions, 0 failures**; complete JavaScript suite **16 passed**; all **17 application JavaScript files** passed syntax checks. Route registration, Blade compilation and Git whitespace checks passed. Added tests cover all six roles, ownership, filtering, legacy/destination compatibility, CSRF, pagination recovery, failed requests and confirmation before deletion.

Browser/mobile visual inspection, keyboard/modal interaction and live polling against multiple browser tabs remain manual checks. No browser or end-to-end pass is claimed. Unrelated application-wizard/public-navbar stylesheet edits were preserved; no changes were staged, committed or pushed.

## All-role incoming updates

The shared bell/dropdown poller now runs for all six roles through an authenticated, rate-limited recipient-scoped endpoint. The legacy cashier endpoint remains cashier-only for compatibility. Polling checks approximately every five seconds, skips hidden tabs, and stops on authentication/authorization/CSRF failures. It never marks incoming notifications read.

The full page updates its tab counts and reveals a **Show new updates** link when new records arrive. The link preserves search, type, read state and row limit while returning to page one; existing cards are not replaced while the user is reading. Live visual behavior and cross-tab polling still require browser verification. Focused PHP checks: **13 tests, 208 assertions passed**. Complete JavaScript checks after this extension: **19 passed**. Blade compilation, polling JavaScript syntax and notification route registration passed.
