@extends('adminlte::page')

@section('title', 'Review Enrollment')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-10" >
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Review Enrollment</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Review the applicant's details and selected subjects before making a decision.
            </p>
        </div>
        <a href="{{ route('registrar.enrollments.index') }}" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left"></i> Back to List
        </a>
    </div>
@endsection

@section('content')



<div class="dtc-card dtc-review-standalone">
    @include('registrar.enrollments._review-content', ['enrollment' => $enrollment, 'subjects' => $subjects])
</div>

@push('js')
<script>
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
</script>
@endpush
@endsection