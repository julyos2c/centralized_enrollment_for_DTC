# PWA installation

The public, authentication and portal layouts link to `manifest.webmanifest` and register `sw.js`. Icons use the existing college logo. Installation uses the browser's native Install app / Add to Home Screen action; support and prompts vary by browser. On iPhone/iPad, use Safari's Share menu and Add to Home Screen.

Serve the application over HTTPS in production. Localhost is suitable for local development. An ordinary HTTP LAN address is not sufficient for service-worker registration. Host the contents of `public/` as the web root, and serve `.webmanifest` as `application/manifest+json` (or JSON) and `sw.js` as JavaScript. Keep the worker on the application origin even when assets use a CDN.

The app opens its normal public entry page and uses existing authentication. Session expiration and role access checks remain server enforced. Installation does not add push notifications.

## Offline behavior

Only the static, generic `offline.html` notice is cached. Navigation uses the network; a failed network request shows the notice. No authenticated HTML, uploads, proofs, receipts, API responses or account data is stored in Cache Storage. Writes are never intercepted or queued. An offline submission is not silently retried.

Worker updates use the normal browser lifecycle: close old app tabs/windows to activate a waiting update. There is no forced reload during a form or payment. Increment the worker's cache version when changing the offline notice; activation removes only older caches owned by this worker.

## Release verification

1. In Chrome/Edge DevTools → Application, verify the manifest, icons and active service worker on HTTPS or localhost.
2. Install, launch and sign in. Check account role routing and session expiration.
3. After activation, enable Offline and navigate: only the generic notice should appear. Reconnect and select Try again.
4. Inspect Cache Storage: it should contain only the offline notice, including after visiting payment and document pages.
5. Check that failed offline submissions have not been recorded as successful and that no write request is replayed after reconnecting.
6. Test installation on an actual Android device and Safari Add to Home Screen on iOS. Confirm deployment headers, scope and icon appearance.

Automated coverage in `tests/js/pwa.test.cjs` verifies icon dimensions, manifest fields, registration guards, online-only writes/API calls, and the cache allowlist. Device installation and service-worker lifecycle still require browser checks.
