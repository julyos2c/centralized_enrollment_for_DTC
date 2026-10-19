{{-- Reusable "Showing X to Y of Z" summary line, styled to match dtc-theme.
     Usage: @include('partials.pagination-summary', ['paginator' => $enrollments]) --}}
@if ($paginator->total() > 0)
    <p style="font-size:12px; color:var(--dtc-text-secondary); margin:0;">
        Showing
        <strong  class="u-text">{{ $paginator->firstItem() }}</strong>
        to
        <strong  class="u-text">{{ $paginator->lastItem() }}</strong>
        of
        <strong  class="u-text">{{ $paginator->total() }}</strong>
        {{ Str::plural('result', $paginator->total()) }}
    </p>
@endif