{{-- Shared mobile polish for the review / payment / receipt modals and the
     Process Payments list. Self-contained on purpose: it ships with the views,
     so it renders correctly even if a cached copy of dtc-theme.css is served.
     Included from the modal host partials; @once keeps it to one copy a page. --}}
@once
<style id="dtc-ui-polish">
/* ───────── Modal header: identity left, X top-right, status pill beneath ───────── */
@media (max-width: 767.98px) {
    body .dtc-review-header {
        display: flex; flex-direction: row; flex-wrap: wrap; align-items: flex-start; gap: 10px 12px; padding: 18px 16px;
    }
    body .dtc-review-identity { order: 1; flex: 1 1 0; min-width: 0; }
    body .dtc-review-header-right { display: contents; }
    body .dtc-review-header-right .dtc-review-close {
        order: 2; flex: 0 0 36px; width: 36px; height: 36px; margin-left: auto; align-self: flex-start;
    }
    body .dtc-review-header-right .dtc-status-badge { order: 3; flex: 0 0 auto; margin-right: auto; }
    body .dtc-review-email { overflow-wrap: anywhere; }
    body .dtc-review-body { padding: 18px 16px; }
    body .dtc-review-info-grid { gap: 14px 12px; }
}

/* ───────── Modal footer: equal-width actions, Close beneath, nothing wraps ───────── */
@media (max-width: 575.98px) {
    body .dtc-review-footer { padding: 14px 16px; }
    body .dtc-review-footer-default,
    body .dtc-review-footer-reject {
        display: flex; flex-direction: column-reverse; align-items: stretch; justify-content: flex-start; flex-wrap: nowrap; gap: 10px;
    }
    body .dtc-review-footer-right { display: flex; flex-direction: row; flex-wrap: nowrap; align-items: stretch; width: 100%; gap: 10px; }
    body .dtc-review-footer-right > .dtc-btn,
    body .dtc-review-footer-right > form,
    body .dtc-review-footer-right > button { flex: 1 1 0; min-width: 0; width: auto; }
    body .dtc-review-footer-right form .dtc-btn { width: 100%; }
    body .dtc-review-footer-default > .dtc-btn,
    body .dtc-review-footer-reject > .dtc-btn { width: 100%; }
    body .dtc-review-footer .dtc-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 44px; margin: 0; white-space: nowrap; text-align: center;
    }
    /* Close is a quiet secondary action under the primary row */
    body .dtc-review-footer-default > .dtc-btn-secondary { background: transparent; }
}

/* ───────── Receipt (modal + standalone page) ───────── */
.dtc-receipt-standalone .dtc-review-close { display: none !important; }
.dtc-receipt-standalone .dtc-receipt-body { max-height: none; overflow: visible; }
body .dtc-receipt-header { align-items: center; padding: 16px 20px; }
#printable-receipt-modal > div:first-child { flex-wrap: wrap; gap: 12px 16px; }
body .dtc-receipt-number { font-size: 16px; font-weight: 700; letter-spacing: .3px; overflow-wrap: anywhere; }
body .dtc-receipt-total {
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    margin-top: 14px; padding: 16px 18px; border-radius: 12px;
    background: #0F4CDB; color: #fff; font-size: 13px; font-weight: 600;
}
body .dtc-receipt-total-amount { color: #FFC72C; font-size: 24px; font-weight: 800; white-space: nowrap; }
body .dtc-receipt-confirmed { margin-top: 20px; padding-top: 16px; border-top: 1px dashed var(--dtc-border); text-align: center; }
body .dtc-receipt-confirmed-title { font-size: 14px; font-weight: 700; color: var(--dtc-text); }
@media (max-width: 767.98px) {
    body .dtc-receipt-page-heading { display: block !important; }
    body .dtc-receipt-page-actions { display: flex; gap: 8px; width: 100%; margin-top: 12px; }
    body .dtc-receipt-page-actions .btn { flex: 1 1 0; min-height: 42px; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
    #printable-receipt-modal > div:first-child { display: grid !important; grid-template-columns: 1fr; }
    #printable-receipt-modal > div:first-child > div:last-child { text-align: left !important; }
}

/* ───────── Process Payments list ───────── */
.dtc-pay-search { position: relative; margin-bottom: 12px; }
.dtc-pay-search-input {
    width: 100%; height: 44px; padding: 0 42px; border: 1px solid var(--dtc-border); border-radius: 10px;
    background: var(--dtc-surface); color: var(--dtc-text); font-size: 14px; outline: none; -webkit-appearance: none; appearance: none;
    transition: border-color .15s, box-shadow .15s;
}
.dtc-pay-search-input::placeholder { color: var(--dtc-text-muted); }
.dtc-pay-search-input:focus { border-color: var(--dtc-primary); box-shadow: 0 0 0 3px rgba(15, 76, 219, .18); }
.dtc-pay-search-input::-webkit-search-cancel-button { display: none; }
.dtc-pay-search-icon { position: absolute; left: 14px; top: 50%; width: 18px; height: 18px; transform: translateY(-50%); color: var(--dtc-text-muted); pointer-events: none; }
.dtc-pay-search-clear {
    position: absolute; right: 6px; top: 50%; width: 32px; height: 32px; transform: translateY(-50%);
    display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 8px;
    background: transparent; color: var(--dtc-text-secondary); cursor: pointer;
}
.dtc-pay-search-clear[hidden] { display: none; }
.dtc-pay-search-clear svg { width: 16px; height: 16px; }
.dtc-filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.dtc-pay-fee { font-weight: 700; color: #15803D; white-space: nowrap; }
body.dtc-dark .dtc-pay-fee { color: #4ADE80; }
.dtc-pay-actions { display: flex; align-items: center; justify-content: center; gap: 6px; }
@media (min-width: 768px) { .dtc-pay-search { max-width: 420px; } }

@media (max-width: 767.98px) {
    .dtc-filter-bar { flex-wrap: nowrap; overflow-x: auto; margin: 0 -4px 14px; padding: 2px 4px 6px; scrollbar-width: none; }
    .dtc-filter-bar::-webkit-scrollbar { display: none; }
    .dtc-filter-bar .dtc-filter-pill { flex: 0 0 auto; white-space: nowrap; }

    .dtc-payments-table { min-width: 0 !important; width: 100%; }
    .dtc-payments-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    .dtc-payments-table, .dtc-payments-table tbody { display: block; }
    .dtc-payments-table tbody tr {
        display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; padding: 14px 16px;
        border-bottom: 1px solid var(--dtc-border-soft, var(--dtc-border));
    }
    .dtc-payments-table tbody tr:last-child { border-bottom: 0; }
    .dtc-payments-table tbody tr[hidden] { display: none; }
    .dtc-payments-table tbody td {
        display: block; padding: 0 !important; border: 0 !important; background: transparent !important; width: auto; min-width: 0; font-size: 12.5px;
    }
    .dtc-payments-table tbody td[colspan] { width: 100%; flex: 1 0 100%; }
    .dtc-payments-table tbody td:nth-child(1) { order: 1; flex: 1 1 0; }
    .dtc-payments-table tbody td:nth-child(6) { order: 2; flex: 0 0 auto; }
    .dtc-payments-table tbody td:nth-child(2) { order: 3; flex: 1 0 100%; font-weight: 600; }
    .dtc-payments-table tbody td:nth-child(3),
    .dtc-payments-table tbody td:nth-child(4),
    .dtc-payments-table tbody td:nth-child(5) { order: 4; flex: 0 0 auto; }
    .dtc-payments-table tbody td:nth-child(3)::after,
    .dtc-payments-table tbody td:nth-child(4)::after { content: "·"; margin-left: 10px; opacity: .5; }
    .dtc-payments-table tbody tr::after { content: ""; order: 5; flex: 1 0 100%; height: 0; margin: -6px 0 0; }
    .dtc-payments-table tbody td:nth-child(7) { order: 6; flex: 1 1 0; font-size: 18px; margin-top: 6px; }
    .dtc-payments-table tbody td:nth-child(8) { order: 7; flex: 0 0 auto; margin-top: 6px; }
    .dtc-payments-table .dtc-header-btn .dtc-header-btn-label { display: inline; }
    .dtc-payments-table .dtc-header-btn {
        min-height: 40px; padding: 8px 14px !important; gap: 6px; display: inline-flex; align-items: center; justify-content: center;
    }
    .dtc-payments-table .dtc-header-btn i { margin: 0 !important; }
    .dtc-pay-actions { justify-content: flex-end; }
    #paymentNoResults:not([hidden]) { display: block; }
}
</style>
@endonce
