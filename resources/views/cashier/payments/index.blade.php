@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Process Payments')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text" >Process Payments</h4>
        <p class="mb-0 u-text-secondary-sm" >
            Approved enrollments awaiting payment collection
        </p>
    </div>
@endsection

@section('content')


{{-- Filter Tabs --}}
<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
    @foreach ([
        'all'           => 'All',
        'unpaid'        => 'Unpaid',
        'gcash_pending' => 'GCash Pending',
        'verified'      => 'Verified',
        'walk_in'       => 'Walk-in',
        'rejected'      => 'Rejected',
    ] as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['filter' => $key]) }}"
           class="dtc-filter-pill {{ $filter === $key ? 'is-active' : '' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text" >Approved Enrollments</span>
        <span  class="u-text-secondary-sm">{{ $enrollments->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table dtc-table mb-0">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Program</th>
                    <th>Year / Semester</th>
                    <th>Subjects</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Fee</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                <tr>
                    <td>
                        <div  class="u-flex-center-gap-10">
                            <div style="width:32px; height:32px; border-radius:50%;
                                        background:linear-gradient(135deg,#22C55E,#059669);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                {{ strtoupper(substr($enrollment->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-size:13px; font-weight:500; color:var(--dtc-text);">
                                    {{ $enrollment->user->name }}
                                </div>
                                <div  class="u-text-xs-muted">
                                    {{ $enrollment->user->email }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--dtc-text); font-weight:500;">
                        {{ $enrollment->program->name }}
                    </td>
                    <td  class="u-text-secondary">
                        Year {{ $enrollment->year_level }} &middot; Sem {{ $enrollment->semester }}
                    </td>
                    <td>
                        <span class="badge badge-secondary">
                            {{ count($enrollment->subject_ids) }} subjects
                        </span>
                    </td>
                    <td  class="u-text-secondary">
                        @if ($enrollment->payment)
                            {{ $enrollment->payment->isWalkIn() ? 'Walk-in' : 'GCash' }}
                        @else
                            <span  class="u-text-muted">&mdash;</span>
                        @endif
                    </td>
                    <td>
                        @if (! $enrollment->payment)
                            <x-dtc.status-badge status="Unpaid" />
                        @elseif ($enrollment->payment->isPending())
                            <x-dtc.status-badge status="Pending" />
                        @elseif ($enrollment->payment->isVerified())
                            <x-dtc.status-badge status="Verified" />
                        @elseif ($enrollment->payment->isRejected())
                            <x-dtc.status-badge status="Rejected" />
                        @endif
                    </td>
                    <td style="font-weight:700; color:#15803D;">
                        ₱{{ number_format($enrollment->payment->amount ?? \App\Models\Setting::get('enrollment_fee', 500), 2) }}
                    </td>
                    <td>
                        <div class="d-flex flex-row align-items-center justify-content-center" style="gap:6px;">
                            <button type="button" class="dtc-review-btn dtc-payment-btn dtc-header-btn"
                                    data-url="{{ route('cashier.payments.show', $enrollment) }}">
                                <i data-lucide="banknote"></i>
                                <span class="dtc-header-btn-label">Process</span>
                            </button>
                            @if ($enrollment->is_paid)
                                <button type="button" class="dtc-review-btn dtc-receipt-btn dtc-header-btn"
                                        data-url="{{ route('cashier.payments.receipt', $enrollment) }}">
                                    <i data-lucide="receipt"></i>
                                    <span class="dtc-header-btn-label">Receipt</span>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 u-text-muted" >
                        No enrollments match the selected filter.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="card-footer u-panel-footer" >
        <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
            <small  class="u-text-secondary">
                Showing
                <strong>{{ $enrollments->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $enrollments->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $enrollments->total() }}</strong>
                results
            </small>

            @if ($enrollments->hasPages())
            <div>
                {{ $enrollments->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>
    </div>
</div>

@include('cashier.payments._payment-modal')
@include('cashier.payments._receipt-modal')

@endsection