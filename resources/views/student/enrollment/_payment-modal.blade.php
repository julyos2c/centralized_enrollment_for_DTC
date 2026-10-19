{{-- Shared "Enrollment Payment" modal.
     Include this once per page anywhere a `.dtc-payment-btn` (with a
     `data-url` pointing at the enrollment's payment-info route) may appear —
     e.g. the new-applicant/student dashboard "Next Step" card and the
     enrollment status list both point at the same modal instance. --}}
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content dtc-review-modal-content" id="paymentModalContent">
            <div class="dtc-review-loading">
                <div class="dtc-spinner u-spinner-sm" ></div>
                <p>Loading payment details…</p>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    var $modal        = $('#paymentModal');
    var $modalContent = $('#paymentModalContent');

    var loadingMarkup = '\
        <div class="dtc-review-loading">\
            <div class="dtc-spinner u-spinner-sm" ></div>\
            <p>Loading payment details...</p>\
        </div>';

    var errorMarkup = '\
        <div class="dtc-review-loading">\
            <i data-lucide="alert-triangle" class="u-danger-lg"></i>\
            <p class="text-danger mb-0">Couldn\'t load payment details. Please try again.</p>\
        </div>';

    // Open modal + lazy-load its content from the existing payment-info route.
    $(document).on('click', '.dtc-payment-btn', function () {
        var url = $(this).data('url');

        $modalContent.html(loadingMarkup);
        $modal.modal('show');

        $.get(url)
            .done(function (html) {
                var content = $('<div>').html(html).find('#payment-content').html();
                $modalContent.html(content || errorMarkup);
            })
            .fail(function () {
                $modalContent.html(errorMarkup);
            });
    });

    // Reset to the loading state once the modal has fully closed, so it
    // doesn't briefly flash stale payment status on next open.
    $modal.on('hidden.bs.modal', function () {
        $modalContent.html(loadingMarkup);
    });
})();
</script>
@endpush
