{{-- Shared "Review Enrollment" modal.
     Include this once per page anywhere a `.dtc-review-btn` (with a
     `data-url` pointing at the enrollment's show route) may appear —
     e.g. the registrar dashboard widget and the enrollments index table
     both point at the same modal instance. --}}
<div class="modal fade" id="enrollmentReviewModal" tabindex="-1" role="dialog" aria-labelledby="enrollmentReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content dtc-review-modal-content" id="enrollmentReviewModalContent">
            <div class="dtc-review-loading">
                <div class="dtc-spinner u-spinner-sm" ></div>
                <p>Loading enrollment details…</p>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    var $modal        = $('#enrollmentReviewModal');
    var $modalContent = $('#enrollmentReviewModalContent');

    var loadingMarkup = '\
        <div class="dtc-review-loading">\
            <div class="dtc-spinner u-spinner-sm" ></div>\
            <p>Loading enrollment details...</p>\
        </div>';

    var errorMarkup = '\
        <div class="dtc-review-loading">\
            <i data-lucide="alert-triangle" class="u-danger-lg"></i>\
            <p class="text-danger mb-0">Couldn\'t load this enrollment. Please try again.</p>\
        </div>';

    // Open modal + lazy-load its content from the existing "show" route.
    $(document).on('click', '.dtc-review-btn', function () {
        var url = $(this).data('url');

        $modalContent.html(loadingMarkup);
        $modal.modal('show');

        $.get(url)
            .done(function (html) {
                var content = $('<div>').html(html).find('#review-content').html();
                $modalContent.html(content || errorMarkup);
            })
            .fail(function () {
                $modalContent.html(errorMarkup);
            });
    });

    // Reset to the loading state once the modal has fully closed, so the
    // next enrollment doesn't briefly flash the previous one's details.
    $modal.on('hidden.bs.modal', function () {
        $modalContent.html(loadingMarkup);
    });

    // Reveal the inline reject-reason panel (event delegation - this
    // content is injected dynamically, so listeners are bound on document).
    $(document).on('click', '.dtc-review-reject-trigger', function () {
        var targetId = $(this).data('target');
        $('#' + targetId).addClass('is-open').slideDown(160);

        var $footer = $(this).closest('.dtc-review-footer');
        $footer.find('.dtc-review-footer-default').hide();
        $footer.find('.dtc-review-footer-reject').fadeIn(160);
    });

    $(document).on('click', '.dtc-review-reject-cancel', function () {
        $('.dtc-review-reject-panel').slideUp(160).removeClass('is-open');

        var $footer = $(this).closest('.dtc-review-footer');
        $footer.find('.dtc-review-footer-reject').hide();
        $footer.find('.dtc-review-footer-default').fadeIn(160);
    });
})();
</script>
@endpush