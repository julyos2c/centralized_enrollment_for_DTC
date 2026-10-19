<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function enrollmentReport()
    {
        $enrollments = Enrollment::with('user', 'program')->latest()->get();
        $pdf = Pdf::loadView('admin.reports.enrollment', compact('enrollments'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('DTC_Enrollment_Report_' . date('Ymd') . '.pdf');
    }

    public function paymentReport()
    {
       $payments = Payment::with('enrollment.user', 'enrollment.program', 'cashier', 'verifier')
        ->latest()
        ->get();
        $totalRevenue = $payments->where('status', 'verified')->sum('amount');
        $verifiedCount = $payments->where('status', 'verified')->count();
        $pdf = Pdf::loadView('admin.reports.payment', compact('payments', 'totalRevenue', 'verifiedCount'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('DTC_Payment_Report_' . date('Ymd') . '.pdf');
    }
}