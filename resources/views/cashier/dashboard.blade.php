@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Cashier Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">Cashier Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Manage payments and receipts</p>
        </div>
        <a href="{{ route('cashier.payments.index') }}" class="dtc-btn dtc-btn-primary dtc-header-btn">
            <i data-lucide="banknote"></i>
            <span class="dtc-header-btn-label">Process Payments</span>
        </a>
    </div>
@endsection

@section('content')

{{-- KPI ROW --}}
<div class="row mb-2">
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="coins" color="primary"
            label="Today's Collection" value="₱{{ number_format($todayCollection, 2) }}"
            note="Total Amount" />
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="clock" color="warning"
            label="Pending Payments" value="{{ $pendingPayments }}"
            href="{{ route('cashier.payments.index') }}" link-text="For Processing" />
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="check-circle" color="success"
            label="Paid This Month" value="{{ $paidThisMonth }}"
            note="Transactions" />
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="footprints" color="info"
            label="Walk-in Payments" value="{{ $walkinPayments }}"
            note="This Month" />
    </div>
</div>

{{-- MAIN ROW --}}
<div class="row">

    {{-- Recent Payments Table --}}
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Recent Payments</span>
                <a href="{{ route('cashier.payments.index') }}"
                   style="font-size:12px; color:var(--dtc-primary); text-decoration:none;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Payer</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                        <tr>
                            <td style="font-size:12px; font-weight:600; color:var(--dtc-primary);">
                                {{ $payment->receipt_no }}
                            </td>
                            <td>
                                <div  class="u-flex-center-gap-10">
                                    <div style="width:32px; height:32px; border-radius:50%;
                                                background:linear-gradient(135deg,#22C55E,#059669);
                                                display:flex; align-items:center; justify-content:center;
                                                color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                        {{ strtoupper(substr($payment->enrollment->user->name, 0, 1)) }}
                                    </div>
                                    <span  class="u-text-sm">{{ $payment->enrollment->user->name }}</span>
                                </div>
                            </td>
                            <td style="font-size:13px; font-weight:600; color:var(--dtc-success);">
                                ₱{{ number_format($payment->amount, 2) }}
                            </td>
                            <td  class="u-text-xxs-secondary">
                                {{ $payment->paid_at->format('M d, Y') }}
                            </td>
                            <td><x-dtc.status-badge status="Paid" /></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:var(--dtc-text-muted); font-size:13px;">
                                No payments yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            @if ($recentPayments->total() > 0)
                <div class="card-footer u-panel-footer">
                    <a href="{{ route('cashier.payments.index') }}" class="u-text-sm" style="color:var(--dtc-primary); text-decoration:none;">View All Payments →</a>
                </div>
            @endif
        </div>
    </div>

    {{-- Right Column --}}
    <div class="col-lg-4 mb-3">

        {{-- Collection Overview Donut --}}
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Collection Overview</div>
            <div class="card-body">
                <div style="position:relative; width:160px; height:160px; margin:0 auto 16px;">
                    <canvas id="collectionChart"></canvas>
                    <div style="position:absolute; top:50%; left:50%;
                                transform:translate(-50%,-50%); text-align:center;">
                        <div style="font-size:16px; font-weight:700;">
                            ₱{{ number_format($totalRevenue, 0) }}
                        </div>
                        <div style="font-size:10px; color:var(--dtc-text-secondary); font-weight:600;">TOTAL</div>
                    </div>
                </div>
                <div  class="u-text-sm">
                    <div class="d-flex justify-content-between mb-2">
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0F4CDB;margin-right:6px;"></span>Paid</span>
                        <span class="font-weight-bold">{{ $totalPaid }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#FFC72C;margin-right:6px;"></span>Pending</span>
                        <span class="font-weight-bold">{{ $pendingPayments }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card">
            <div class="card-header font-weight-bold">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('cashier.payments.index') }}" class="quick-action-btn">
                    <i data-lucide="banknote"></i> Process Payments
                </a>
                <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                    <i data-lucide="bell"></i> Notifications
                </a>
            </div>
        </div>

    </div>
</div>

{{-- Today's Summary --}}
<div class="row">
    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold">Today's Summary</div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-3 mb-md-0">
                        <div style="font-size:22px; font-weight:700; color:var(--dtc-primary);">{{ $totalTransactions }}</div>
                        <div  class="u-text-xxs-secondary-bold">Total Transactions</div>
                    </div>
                    <div class="col-6 col-md-3 mb-3 mb-md-0">
                        <div style="font-size:22px; font-weight:700; color:var(--dtc-success);">{{ $totalPaid }}</div>
                        <div  class="u-text-xxs-secondary-bold">Paid</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:22px; font-weight:700; color:var(--dtc-warning);">{{ $pendingPayments }}</div>
                        <div  class="u-text-xxs-secondary-bold">Pending</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:22px; font-weight:700;">₱{{ number_format($totalRevenue, 2) }}</div>
                        <div  class="u-text-xxs-secondary-bold">Total Amount</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('collectionChart');
    if (ctx) {
        const chartTheme = getDtcChartTheme();
        registerDtcChart(new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Pending'],
                datasets: [{
                    data: [{{ $totalPaid }}, {{ $pendingPayments }}],
                    backgroundColor: ['#0F4CDB', '#FFC72C'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: chartTheme.tooltipBg,
                        titleColor: chartTheme.tooltipText,
                        bodyColor: chartTheme.tooltipText,
                    }
                }
            }
        }));
    }
});
</script>
@endsection