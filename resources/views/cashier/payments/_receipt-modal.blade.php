{{-- Shared "Official Receipt" modal.
     Include this once per page anywhere a `.dtc-receipt-btn` (with a
     `data-url` pointing at the enrollment's cashier receipt route) may
     appear — e.g. the payment-processing modal's "already paid" branch,
     or a "Receipt" action in the payments index table. --}}
<div class="modal fade" id="receiptModal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered dtc-receipt-modal-dialog" role="document">
        <div class="modal-content dtc-review-modal-content" id="receiptModalContent">
            <div class="dtc-review-loading">
                <div class="dtc-spinner u-spinner-sm" ></div>
                <p>Loading receipt…</p>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    var $modal        = $('#receiptModal');
    var $modalContent = $('#receiptModalContent');

    var loadingMarkup = '\
        <div class="dtc-review-loading">\
            <div class="dtc-spinner u-spinner-sm" ></div>\
            <p>Loading receipt...</p>\
        </div>';

    var errorMarkup = '\
        <div class="dtc-review-loading">\
            <i data-lucide="alert-triangle" class="u-danger-lg"></i>\
            <p class="text-danger mb-0">Couldn\'t load this receipt. Please try again.</p>\
        </div>';

    $(document).on('click', '.dtc-receipt-btn', function () {
        var url = $(this).data('url');

        // If this was opened from another modal (e.g. the payment modal's
        // "already paid" branch), hide it first so they don't stack.
        $('.modal.show').not($modal).modal('hide');

        $modalContent.html(loadingMarkup);
        $modal.modal('show');

        $.ajax({
            url: url,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (html) {
                var $parsed = $('<div>').html(html);
                var content = $parsed.find('#receipt-content').html();
                if (content) {
                    $modalContent.html(content);
                    if (window.lucide) lucide.createIcons({ nodes: $modalContent[0].querySelectorAll('[data-lucide]') });
                } else {
                    $modalContent.html(errorMarkup);
                }
            },
            error: function () {
                $modalContent.html(errorMarkup);
                if (window.lucide) lucide.createIcons({ nodes: $modalContent[0].querySelectorAll('[data-lucide]') });
            }
        });
    });

    $modal.on('hidden.bs.modal', function () {
        $modalContent.html(loadingMarkup);
    });

    // Print just the receipt. Builds a self-contained, print-safe document
    // (table layout, no dependence on the app stylesheet, backgrounds or
    // icon fonts) so it renders the same in desktop and mobile print/PDF.
    window.printReceiptModal = function () {
        var root = document.getElementById('printable-receipt-modal');
        if (! root) { return; }

        function esc(t) {
            return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }
        function txt(el) { return el ? el.textContent.replace(/\s+/g, ' ').trim() : ''; }
        function q(sel) { return root.querySelector(sel); }

        var receiptNo = txt(q('.dtc-receipt-number'));
        var dateIssued = txt(q('.dtc-receipt-date'));
        var total = txt(q('.dtc-receipt-total-amount'));
        var confirmTitle = txt(q('.dtc-receipt-confirmed-title'));

        var sectionsHtml = '';
        root.querySelectorAll('.dtc-review-section').forEach(function (sec) {
            var title = txt(sec.querySelector('.dtc-review-section-title'));
            var rows = '';
            sec.querySelectorAll('.dtc-review-info').forEach(function (info) {
                rows += '<tr><th>' + esc(txt(info.querySelector('.dtc-review-info-label'))) + '</th>' +
                        '<td>' + esc(txt(info.querySelector('.dtc-review-info-value'))) + '</td></tr>';
            });
            sectionsHtml += '<h3>' + esc(title) + '</h3><table class="info">' + rows + '</table>';
            if (sec.querySelector('.dtc-receipt-total')) {
                sectionsHtml += '<table class="total"><tr><td>Total Amount Paid</td>' +
                                '<td class="amt">' + esc(total) + '</td></tr></table>';
            }
        });

        var logo = location.origin + '/images/DTC-LOGO.webp';
        var css =
            '@page { size: A5 portrait; margin: 12mm; }' +
            '* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }' +
            'html, body { margin:0; padding:0; background:#fff; color:#1e293b; font-family: Arial, Helvetica, sans-serif; font-size:10.5pt; line-height:1.4; }' +
            '.sheet { width:100%; max-width:720px; margin:0 auto; }' +
            '.head { width:100%; border-collapse:collapse; border-bottom:3px solid #0f4cdb; margin-bottom:14px; }' +
            '.head td { padding:0 0 10px; vertical-align:middle; }' +
            '.head img { width:52px; height:52px; object-fit:contain; display:block; }' +
            '.head .org { padding-left:12px; }' +
            '.org h1 { margin:0; font-size:15pt; color:#0f4cdb; letter-spacing:.3px; }' +
            '.org p { margin:2px 0 0; font-size:9.5pt; color:#64748b; }' +
            '.meta { width:100%; border-collapse:collapse; margin-bottom:6px; border-bottom:1px dashed #94a3b8; }' +
            '.meta td { padding:0 0 10px; vertical-align:top; }' +
            '.meta .r { text-align:right; }' +
            '.lbl { display:block; font-size:7.5pt; letter-spacing:.6px; text-transform:uppercase; color:#64748b; margin-bottom:2px; }' +
            '.no { font-size:12pt; font-weight:bold; color:#0f4cdb; }' +
            '.dt { font-size:10pt; font-weight:bold; }' +
            'h3 { margin:16px 0 6px; font-size:9pt; letter-spacing:.6px; text-transform:uppercase; color:#0f4cdb; }' +
            'table.info { width:100%; border-collapse:collapse; border:1px solid #e2e8f0; }' +
            'table.info th, table.info td { padding:7px 10px; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; font-size:10pt; }' +
            'table.info tr:last-child th, table.info tr:last-child td { border-bottom:0; }' +
            'table.info th { width:38%; font-weight:normal; color:#64748b; background:#f8fafc; font-size:9pt; }' +
            'table.info td { font-weight:bold; overflow-wrap:anywhere; word-break:break-word; }' +
            'table.total { width:100%; border-collapse:collapse; margin-top:12px; border:2px solid #0f4cdb; background:#0f4cdb; color:#fff; }' +
            'table.total td { padding:11px 14px; font-weight:bold; font-size:10.5pt; }' +
            'table.total td.amt { text-align:right; font-size:16pt; color:#ffc72c; white-space:nowrap; }' +
            '.ok { margin-top:18px; text-align:center; padding-top:12px; border-top:1px dashed #94a3b8; }' +
            '.ok b { display:block; font-size:11pt; color:#15803d; }' +
            '.ok span { display:block; margin-top:3px; font-size:8.5pt; color:#64748b; }' +
            'table, tr { break-inside:avoid; page-break-inside:avoid; }';

        var html =
            '<!doctype html><html><head><meta charset="utf-8">' +
            '<meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<title>Official Receipt ' + esc(receiptNo) + '</title><style>' + css + '</style></head><body>' +
            '<div class="sheet">' +
                '<table class="head"><tr>' +
                    '<td style="width:52px"><img src="' + esc(logo) + '" alt="" onerror="this.style.display=\'none\'"></td>' +
                    '<td class="org org-cell"><div class="org"><h1>Official Receipt</h1><p>Danao Technological College</p></div></td>' +
                '</tr></table>' +
                '<table class="meta"><tr>' +
                    '<td><span class="lbl">Receipt No.</span><span class="no">' + esc(receiptNo) + '</span></td>' +
                    '<td class="r"><span class="lbl">Date Issued</span><span class="dt">' + esc(dateIssued) + '</span></td>' +
                '</tr></table>' +
                sectionsHtml +
                '<div class="ok"><b>' + esc(confirmTitle) + '</b></div>' +
            '</div></body></html>';

        var win = window.open('', '_blank');
        if (! win) { return; }
        win.document.open();
        win.document.write(html);
        win.document.close();
        win.focus();
        var done = false;
        function go() { if (done) { return; } done = true; win.print(); }
        win.onload = function () { setTimeout(go, 150); };
        setTimeout(go, 1200);
    };
})();
</script>
@endpush
