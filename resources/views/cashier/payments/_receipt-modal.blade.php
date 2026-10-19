{{-- Shared "Official Receipt" modal.
     Include this once per page anywhere a `.dtc-receipt-btn` (with a
     `data-url` pointing at the enrollment's cashier receipt route) may
     appear — e.g. the payment-processing modal's "already paid" branch,
     or a "Receipt" action in the payments index table. --}}
<div class="modal fade" id="receiptModal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
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

    // Print just the receipt: open a clean window with light, print-safe
    // styling rather than printing the whole app shell behind the modal.
    window.printReceiptModal = function () {
        var content = document.getElementById('printable-receipt-modal');
        if (! content) { return; }

        var win = window.open('', '_blank', 'width=650,height=800');
        win.document.write(
            '<html>' +
            '<head>' +
                '<title>Official Receipt</title>' +
                '<style>' +
                    'body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; color:#1E293B; padding:24px; }' +
                    '* { box-sizing: border-box; }' +
                '</style>' +
            '</head>' +
            '<body>' + content.innerHTML + '</body>' +
            '</html>'
        );
        win.document.close();
        win.focus();
        win.onload = function () { win.print(); };
    };
})();
</script>
@endpush
