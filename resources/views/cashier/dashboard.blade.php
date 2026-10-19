@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Cashier Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Cashier Dashboard</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">Manage payments and receipts</p>
        </div>
        <a href="{{ route('cashier.payments.index') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-money-bill-wave mr-1"></i> Process Payments
        </a>
    </div>
@endsection

@section('content')

{{-- STAT CARDS --}}
<div class="row mb-2">
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-blue">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value" style="font-size:20px;">₱{{ number_format($todayCollection, 2) }}</div>
            <div class="stat-label">Today's Collection</div>
            <div class="stat-footer">Total Amount</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-yellow">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value">{{ $pendingPayments }}</div>
            <div class="stat-label">Pending Payments</div>
            <div class="stat-footer">For Processing</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value">{{ $paidThisMonth }}</div>
            <div class="stat-label">Paid This Month</div>
            <div class="stat-footer">Transactions</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-purple">
            <div class="stat-icon"><i class="fas fa-walking"></i></div>
            <div class="stat-value">{{ $walkinPayments }}</div>
            <div class="stat-label">Walk-in Payments</div>
            <div class="stat-footer">This Month</div>
        </div>
    </div>
</div>

{{-- MAIN ROW --}}
<div class="row">

    {{-- Recent Payments Table --}}
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:#1E293B;">Recent Payments</span>
                <a href="{{ route('cashier.payments.index') }}"
                   style="font-size:12px; color:#0F4CDB; text-decoration:none;">View All</a>
            </div>
            <div class="card-body p-0">
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
                            <td style="font-size:12px; font-weight:600; color:#0F4CDB;">
                                {{ $payment->receipt_no }}
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:32px; height:32px; border-radius:50%;
                                                background:linear-gradient(135deg,#22C55E,#059669);
                                                display:flex; align-items:center; justify-content:center;
                                                color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                        {{ strtoupper(substr($payment->enrollment->user->name, 0, 1)) }}
                                    </div>
                                    <span style="font-size:13px;">{{ $payment->enrollment->user->name }}</span>
                                </div>
                            </td>
                            <td style="font-size:13px; font-weight:600; color:#15803D;">
                                ₱{{ number_format($payment->amount, 2) }}
                            </td>
                            <td style="font-size:12px; color:#64748B;">
                                {{ $payment->paid_at->format('M d, Y') }}
                            </td>
                            <td><span class="badge badge-success">Paid</span></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:#94A3B8; font-size:13px;">
                                No payments yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right Column --}}
    <div class="col-lg-4 mb-3">

        {{-- Collection Overview Donut --}}
        <div class="card mb-3">
            <div class="card-header font-weight-bold" style="color:#1E293B;">Collection Overview</div>
            <div class="card-body">
                <div style="position:relative; width:160px; height:160px; margin:0 auto 16px;">
                    <canvas id="collectionChart"></canvas>
                    <div style="position:absolute; top:50%; left:50%;
                                transform:translate(-50%,-50%); text-align:center;">
                        <div style="font-size:16px; font-weight:700; color:#1E293B;">
                            ₱{{ number_format($totalRevenue, 0) }}
                        </div>
                        <div style="font-size:10px; color:#64748B; font-weight:600;">TOTAL</div>
                    </div>
                </div>
                <div style="font-size:13px;">
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
            <div class="card-header font-weight-bold" style="color:#1E293B;">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('cashier.payments.index') }}" class="quick-action-btn">
                    <i class="fas fa-money-bill-wave"></i> Process Payments
                </a>
                <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                    <i class="fas fa-bell"></i> Notifications
                </a>
            </div>
        </div>

    </div>
</div>

{{-- Today's Summary --}}
<div class="row">
    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">Today's Summary</div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <div style="font-size:22px; font-weight:700; color:#0F4CDB;">{{ $totalTransactions }}</div>
                        <div style="font-size:12px; color:#64748B; font-weight:500;">Total Transactions</div>
                    </div>
                    <div class="col-3">
                        <div style="font-size:22px; font-weight:700; color:#22C55E;">{{ $totalPaid }}</div>
                        <div style="font-size:12px; color:#64748B; font-weight:500;">Paid</div>
                    </div>
                    <div class="col-3">
                        <div style="font-size:22px; font-weight:700; color:#F59E0B;">{{ $pendingPayments }}</div>
                        <div style="font-size:12px; color:#64748B; font-weight:500;">Pending</div>
                    </div>
                    <div class="col-3">
                        <div style="font-size:22px; font-weight:700; color:#1E293B;">₱{{ number_format($totalRevenue, 2) }}</div>
                        <div style="font-size:12px; color:#64748B; font-weight:500;">Total Amount</div>
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
        new Chart(ctx.getContext('2d'), {
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
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>
@endsection