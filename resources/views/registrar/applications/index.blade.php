@extends('adminlte::page')
@extends('partials.navbar')
@section('title', 'Applications')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text">Applications</h4>
        <p class="mb-0 u-text-secondary-sm">
            Pre-enrollment applications for Registrar review.
        </p>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset_v('css/dtc-filters.css') }}">
@endpush

@push('js')
    <script src="{{ asset_v('js/dtc-filters.js') }}"></script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace">


{{-- Search & Filter --}}
@php
    $appFilterCount = collect(['type', 'program_id', 'status'])->filter(fn ($k) => filled(request($k)))->count();
    $appHasFilters  = $appFilterCount > 0 || filled(request('search'));
@endphp
<form method="GET" action="{{ route('registrar.applications.index') }}" id="applications-filter-form" class="dtc-fl" data-dtc-filters aria-label="Filter applications">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search">
            <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
            <input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}"
                   placeholder="Search name, email or reference…" autocomplete="off" aria-label="Search applications" data-dtc-filter-search>
        </div>
        <button type="button" class="dtc-fl-toggle {{ $appFilterCount ? 'has-filters' : '' }}" data-toggle="collapse" data-target="#applicationsFilterPanel"
                aria-expanded="{{ $appFilterCount ? 'true' : 'false' }}" aria-controls="applicationsFilterPanel" data-dtc-filter-toggle>
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
            <span>Filters</span>
            <span class="dtc-fl-count" data-dtc-filter-count @if(!$appFilterCount) hidden @endif>{{ $appFilterCount }}</span>
        </button>
    </div>

    <div class="collapse {{ $appFilterCount ? 'show' : '' }}" id="applicationsFilterPanel">
        <div class="dtc-fl-panel">
            <div class="dtc-fl-field">
                <label for="filter-type">Applicant type</label>
                <select name="type" id="filter-type" class="form-control" data-dtc-filter>
                    <option value="">All types</option>
                    @foreach([
                        'new_student'    => 'New Student / First Year',
                        'transferee'     => 'Transferee',
                        'shiftee'        => 'Shiftee',
                        'returnee'       => 'Returnee / Readmitted',
                        'cross_enrollee' => 'Cross-Enrollee',
                    ] as $val => $label)
                        <option value="{{ $val }}" {{ request('type') === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="dtc-fl-field">
                <label for="filter-program">Program</label>
                <select name="program_id" id="filter-program" class="form-control" data-dtc-filter>
                    <option value="">All programs</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" {{ (string) request('program_id') === (string) $program->id ? 'selected' : '' }}>
                            {{ $program->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="dtc-fl-field">
                <label for="filter-app-status">Status</label>
                <select name="status" id="filter-app-status" class="form-control" data-dtc-filter>
                    <option value="">All statuses</option>
                    @foreach([
                        'submitted'         => 'Submitted',
                        'under_review'      => 'Under Review',
                        'revision_required' => 'Revision Required',
                        'approved'          => 'Approved',
                        'rejected'          => 'Rejected',
                    ] as $val => $label)
                        <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            @include('partials.table-rows', ['tableRecords' => $applications, 'tableId' => 'registrar-table-1', 'fixedRows' => true])
<div class="dtc-fl-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('registrar.applications.index') }}" class="dtc-fl-clear" data-dtc-clear @if(!$appHasFilters) hidden @endif>Clear all</a>
            </div>
        </div>
    </div>
</form>

{{-- Applications Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text">All Applications</span>
        <span class="u-text-secondary-sm" id="applications-count">{{ $applications->total() }} total</span>
    </div>

    <div id="applications-table-wrapper">

        @php
            $applicantTypeLabels = [
                'new_student'    => 'New Student / First Year',
                'transferee'     => 'Transferee',
                'shiftee'        => 'Shiftee',
                'returnee'       => 'Returnee / Readmitted',
                'cross_enrollee' => 'Cross-Enrollee',
            ];
            $statusVariants = [
                'submitted'         => 'neutral',
                'under_review'      => 'warning',
                'revision_required' => 'warning',
                'approved'          => 'success',
                'rejected'          => 'danger',
            ];
        @endphp

        {{-- ── Desktop table (hidden on mobile) ── --}}
        <div class="card-body p-0 d-none d-md-block">
            <div class="table-responsive">

<table id="registrar-table-1" class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Reference No.</th>
                            <th>Applicant</th>
                            <th>Applicant Type</th>
                            <th>Intended Program</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $application)
                        <tr>
                            <td>{{ $application->reference_no }}</td>
                            <td>
                                {{ $application->full_name }}
                            </td>
                            <td>{{ $applicantTypeLabels[$application->academic_status] ?? ucwords(str_replace('_', ' ', $application->academic_status)) }}</td>
                            <td>{{ $application->program->name ?? '—' }}</td>
                            <td>{{ $application->email }}</td>
                            <td>
                                <x-dtc.status-badge :status="$application->status" :variant="$statusVariants[$application->status] ?? 'neutral'" />
                            </td>
                            <td style="white-space:nowrap;">{{ $application->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('registrar.applications.show', $application) }}" class="btn btn-sm btn-primary">
                                    Review
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                @if(request()->anyFilled(['search','type','status','program_id']))
                                    No applications match your filters.
                                @else
                                    No applications found.
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Mobile cards (hidden on desktop) ── --}}
        <div class="d-block d-md-none">
            @forelse ($applications as $application)
            <div class="dtc-app-card">
                <div class="dtc-app-card-header">
                    <div>
                        <div class="dtc-app-card-ref">{{ $application->reference_no }}</div>
                        <div class="dtc-app-card-name">
                            {{ $application->full_name }}
                        </div>
                    </div>
                    <x-dtc.status-badge :status="$application->status" :variant="$statusVariants[$application->status] ?? 'neutral'" />
                </div>
                <div class="dtc-app-card-body">
                    <div class="dtc-app-card-row">
                        <span class="dtc-app-card-label">Type</span>
                        <span class="dtc-app-card-value">{{ $applicantTypeLabels[$application->academic_status] ?? ucwords(str_replace('_', ' ', $application->academic_status)) }}</span>
                    </div>
                    <div class="dtc-app-card-row">
                        <span class="dtc-app-card-label">Program</span>
                        <span class="dtc-app-card-value">{{ $application->program->name ?? '—' }}</span>
                    </div>
                    <div class="dtc-app-card-row">
                        <span class="dtc-app-card-label">Email</span>
                        <span class="dtc-app-card-value">{{ $application->email }}</span>
                    </div>
                    <div class="dtc-app-card-row">
                        <span class="dtc-app-card-label">Submitted</span>
                        <span class="dtc-app-card-value">{{ $application->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
                <div class="dtc-app-card-footer">
                    <a href="{{ route('registrar.applications.show', $application) }}" class="btn btn-sm btn-primary w-100">
                        <i data-lucide="eye" style="width:13px;height:13px;margin-right:4px;"></i>Review Application
                    </a>
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-5">
                @if(request()->anyFilled(['search','type','status','program_id']))
                    No applications match your filters.
                @else
                    No applications found.
                @endif
            </div>
            @endforelse
        </div>

        @if($applications->hasPages())
        <div class="card-footer u-panel-footer">
            <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15">
                <small class="u-text-secondary">
                    Showing
                    <strong>{{ $applications->firstItem() }}</strong>
                    to
                    <strong>{{ $applications->lastItem() }}</strong>
                    of
                    <strong>{{ $applications->total() }}</strong>
                    applications
                </small>
                <div class="applications-pagination">
                    {{ $applications->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
        @endif
    </div>{{-- /#applications-table-wrapper --}}
</div>


</div>
@endsection

@section('js')
<script>
(function () {
    var searchInput  = document.querySelector('input[name="search"]');
    var typeSelect   = document.querySelector('select[name="type"]');
    var statusSelect = document.querySelector('select[name="status"]');
    var programSelect = document.querySelector('select[name="program_id"]');
    var wrapper      = document.getElementById('applications-table-wrapper');
    var countEl      = document.getElementById('applications-count');
    var baseUrl      = '{{ route("registrar.applications.index") }}';
    var debounceTimer  = null;
    var currentRequest = null;

    function setLoading(on) {
        wrapper.style.opacity       = on ? '0.45' : '1';
        wrapper.style.pointerEvents = on ? 'none' : '';
    }

    function swapWrapper(html) {
        var doc      = new DOMParser().parseFromString(html, 'text/html');
        var newWrap  = doc.getElementById('applications-table-wrapper');
        var newCount = doc.getElementById('applications-count');
        if (newWrap)             wrapper.innerHTML   = newWrap.innerHTML;
        if (newCount && countEl) countEl.textContent = newCount.textContent;
        if (window.lucide)       lucide.createIcons();
    }

    function doRequest(url) {
        if (currentRequest) { currentRequest.abort(); currentRequest = null; }
        setLoading(true);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            if (xhr.status === 200) swapWrapper(xhr.responseText);
            else window.dtcHttpFailure(xhr.status);
            setLoading(false);
        };
        xhr.onerror = function () { setLoading(false); window.dtcHttpFailure(0); };
        xhr.send();
        currentRequest = xhr;
    }

    function buildUrl(extra) {
        var p = new URLSearchParams(extra || {});
        p.set('search', searchInput.value.trim());
        p.set('type',   typeSelect.value);
        p.set('status', statusSelect.value);
        p.set('program_id', programSelect.value);
        p.set('per_page', document.querySelector('[data-table-rows-select]')?.value || new URLSearchParams(location.search).get('per_page') || '10');
        return baseUrl + '?' + p.toString();
    }

    function fetchResults() { doRequest(buildUrl()); }

    // Typing - debounced 300 ms
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchResults, 300);
    });

    // Dropdowns - instant
    typeSelect.addEventListener('change',   fetchResults);
    statusSelect.addEventListener('change', fetchResults);
    programSelect.addEventListener('change', fetchResults);

    // Form submit (Enter / Filter button)
    searchInput.closest('form').addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        fetchResults();
    });

    // Delegated pagination - works after AJAX re-renders
    wrapper.addEventListener('click', function (e) {
        var link = e.target.closest('.applications-pagination a');
        if (!link) return;
        e.preventDefault();
        var pageParams = new URL(link.href).searchParams;
        doRequest(buildUrl({ page: pageParams.get('page') }));
    });
})();
</script>
@endsection
