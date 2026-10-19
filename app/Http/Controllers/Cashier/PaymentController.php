<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function index()
    {
        $enrollments = Enrollment::with('user', 'program')
            ->where('status', 'approved')
            ->where('is_paid', false)
            ->latest()
            ->get();

        return view('cashier.payments.index', compact('enrollments'));
    }

    public function show(Enrollment $enrollment)
    {
        $enrollment->load('user', 'program');
        return view('cashier.payments.show', compact('enrollment'));
    }

    public function store(Request $request, Enrollment $enrollment)
    {
        if ($enrollment->status !== 'approved') {
            return back()->withErrors(['error' => 'This enrollment is not yet approved by the Registrar.']);
        }

        if ($enrollment->is_paid) {
            return back()->withErrors(['error' => 'This enrollment has already been paid.']);
        }

        $receiptNo = 'OR-' . date('Ymd') . '-' . Str::upper(Str::random(5));

        Payment::create([
            'enrollment_id' => $enrollment->id,
            'processed_by'  => Auth::id(),
            'amount'        => 500.00,
            'receipt_no'    => $receiptNo,
            'paid_at'       => now(),
        ]);

        $enrollment->update(['is_paid' => true]);

        // Upgrade role to student
        $enrollment->user->syncRoles(['student']);

        // ── Notify Student ─────────────────────────────────────────────
        NotificationService::send(
            $enrollment->user,
            'Payment Confirmed — Enrollment Complete',
            'Your payment of ₱500.00 has been recorded. Receipt No: ' . $receiptNo . '. You are now officially enrolled!',
            'success',
            route('portal.enrollment.index')
        );

        // ── Notify Admins ──────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Payment Received',
                $enrollment->user->name . ' has paid the ₱500.00 enrollment fee. Receipt No: ' . $receiptNo . '.',
                'success',
                route('cashier.payments.index')
            );
        }

        // ── Notify Registrars ──────────────────────────────────────────
        $registrars = User::role('registrar')->get();
        foreach ($registrars as $registrar) {
            NotificationService::send(
                $registrar,
                'Enrollment Payment Received',
                $enrollment->user->name . ' has completed payment and is now officially enrolled.',
                'success',
                route('registrar.enrollments.index')
            );
        }

        return redirect()->route('cashier.payments.receipt', $enrollment)
            ->with('success', 'Payment recorded successfully.');
    }

    public function receipt(Enrollment $enrollment)
    {
        $enrollment->load('user', 'program', 'payment');
        return view('cashier.payments.receipt', compact('enrollment'));
    }
}