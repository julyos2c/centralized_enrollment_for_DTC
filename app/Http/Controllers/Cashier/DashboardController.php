<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;

class DashboardController extends Controller
{
    public function index()
    {
        $todayCollection   = Payment::whereDate('paid_at', today())->sum('amount');
        $pendingPayments   = Enrollment::where('status', 'approved')->where('is_paid', false)->count();
        $paidThisMonth     = Payment::whereMonth('paid_at', now()->month)->count();
        $walkinPayments    = Payment::whereMonth('paid_at', now()->month)->count();
        $totalRevenue      = Payment::sum('amount');
        $totalPaid         = Payment::count();
        $totalTransactions = Payment::count();

        $recentPayments = Payment::with('enrollment.user', 'enrollment.program')
            ->latest()->paginate(5, ['*'], 'recent_page');

        return view('cashier.dashboard', compact(
            'todayCollection',
            'pendingPayments',
            'paidThisMonth',
            'walkinPayments',
            'totalRevenue',
            'totalPaid',
            'totalTransactions',
            'recentPayments'
        ));
    }
}