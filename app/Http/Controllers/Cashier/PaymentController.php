<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StudentProfile;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    // ── index() ───────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Enrollment::where('status', 'approved')
            ->with(['user', 'program', 'payment']);

        $filter = $request->query('filter', 'all');

        $query = match ($filter) {
            'walk_in'       => $query->whereHas('payment', fn($q) => $q->where('payment_method', 'walk_in')),
            'gcash_pending' => $query->whereHas('payment', fn($q) => $q->where('payment_method', 'gcash')->where('status', 'pending')),
            'verified'      => $query->whereHas('payment', fn($q) => $q->where('status', 'verified')),
            'rejected'      => $query->whereHas('payment', fn($q) => $q->where('status', 'rejected')),
            'unpaid'        => $query->where('is_paid', false)->whereDoesntHave('payment', fn($q) => $q->where('status', 'pending')),
            default         => $query,
        };

        $enrollments = $query->latest()->paginate(20)->withQueryString();

        return view('cashier.payments.index', compact('enrollments', 'filter'));
    }

    // ── show() ────────────────────────────────────────────────────

    public function show(Enrollment $enrollment)
    {
        $enrollment->load(['user', 'program', 'payment.verifier']);

        $latestPayment = Payment::where('enrollment_id', $enrollment->id)
            ->latest()
            ->first();

        return view('cashier.payments.show', compact('enrollment', 'latestPayment'));
    }

    // ── store() — walk-in ─────────────────────────────────────────

    public function store(Request $request, Enrollment $enrollment)
    {
        $amount    = (float) Setting::get('enrollment_fee', 500);
        $receiptNo = 'OR-' . date('Ymd') . '-' . Str::upper(Str::random(5));

        DB::transaction(function () use ($enrollment, $amount, $receiptNo) {
            $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if ($lockedEnrollment->status !== 'approved') {
                throw ValidationException::withMessages(['payment' => 'Only approved enrollments can be paid.']);
            }

            if ($lockedEnrollment->is_paid) {
                throw ValidationException::withMessages(['payment' => 'This enrollment is already paid.']);
            }

            $attempts = Payment::where('enrollment_id', $lockedEnrollment->id)
                ->lockForUpdate()
                ->get();

            if ($attempts->contains('status', 'verified')) {
                throw ValidationException::withMessages(['payment' => 'A verified payment already exists for this enrollment. Contact the Cashier if the records do not match.']);
            }

            if ($attempts->contains('status', 'pending')) {
                throw ValidationException::withMessages(['payment' => 'A GCash payment is awaiting verification. Resolve that payment before recording a walk-in payment.']);
            }

            $user = $lockedEnrollment->user;
            if ($user->hasRole('new_applicant')) {
                $approvedApplicationExists = Application::where('user_id', $user->id)
                    ->where('program_id', $lockedEnrollment->program_id)
                    ->where('status', 'approved')
                    ->exists();

                if (! $approvedApplicationExists) {
                    throw ValidationException::withMessages(['payment' => 'This applicant does not have an approved application for the enrollment program.']);
                }
            }

            Payment::create([
                'enrollment_id'    => $lockedEnrollment->id,
                'payment_method'   => 'walk_in',
                'reference_number' => null,
                'proof_of_payment' => null,
                'status'           => 'verified',
                'processed_by'     => Auth::id(),
                'verified_by'      => Auth::id(),
                'verified_at'      => now(),
                'amount'           => $amount,
                'receipt_no'       => $receiptNo,
                'paid_at'          => now(),
            ]);

            $lockedEnrollment->update(['is_paid' => true]);

            if ($user->hasRole('new_applicant')) {
                $user->syncRoles(['student']);
            } elseif (! $user->hasAnyRole(['student', 'alumni', 'admin', 'registrar', 'cashier'])) {
                $user->assignRole('student');
            }

            static::createStudentProfileIfMissing($user, $lockedEnrollment);
        });

        $payment = Payment::where('enrollment_id', $enrollment->id)->latest()->first();

        NotificationService::send(
            $enrollment->user,
            'Payment Confirmed',
            "Your enrollment payment of ₱{$amount} has been recorded. Receipt: {$receiptNo}.",
            'success',
            route('portal.enrollment.payment-info', $enrollment)
        );
        foreach (\App\Models\User::role('admin')->get() as $admin) {
            NotificationService::send($admin, 'Payment Recorded', "Walk-in payment recorded for {$enrollment->user->name}.", 'info', route('cashier.payments.receipt', $enrollment));
        }
        foreach (\App\Models\User::role(['registrar', 'admin'])->get() as $reg) {
            NotificationService::send($reg, 'Payment Recorded', "Walk-in payment recorded for {$enrollment->user->name}.", 'info', route('cashier.payments.receipt', $enrollment));
        }

        return redirect()->route('cashier.payments.receipt', $enrollment)
            ->with('success', 'Payment recorded successfully.');
    }

    // ── verify() — GCash verification ─────────────────────────────

    public function verify(Request $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return redirect()->route('cashier.payments.show', $payment->enrollment_id)
                ->with('error', 'This payment has already been processed.');
        }

        $receiptNo = 'OR-' . date('Ymd') . '-' . Str::upper(Str::random(5));
        $amount    = $payment->amount;

        DB::transaction(function () use ($payment, $receiptNo) {
            $lockedEnrollment = Enrollment::whereKey($payment->enrollment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'pending' || $lockedPayment->payment_method !== 'gcash') {
                abort(422, 'This payment is no longer eligible for verification.');
            }

            if ($lockedEnrollment->status !== 'approved' || $lockedEnrollment->is_paid) {
                abort(422, 'This enrollment is no longer eligible for payment verification.');
            }

            if (Payment::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'verified')
                ->where('id', '!=', $lockedPayment->id)
                ->exists()) {
                abort(422, 'A verified payment already exists for this enrollment.');
            }

            $lockedPayment->update([
                'status'       => 'verified',
                'verified_by'  => Auth::id(),
                'verified_at'  => now(),
                'processed_by' => Auth::id(),
                'receipt_no'   => $receiptNo,
                'paid_at'      => now(),
            ]);

            $lockedEnrollment->update(['is_paid' => true]);
            $user = $lockedEnrollment->user;
            if ($user->hasRole('new_applicant')) {
                $approvedApplicationExists = Application::where('user_id', $user->id)
                    ->where('program_id', $lockedEnrollment->program_id)
                    ->where('status', 'approved')
                    ->exists();

                if (! $approvedApplicationExists) {
                    abort(422, 'This applicant does not have an approved application for the enrollment program.');
                }

                $user->syncRoles(['student']);
            } elseif (! $user->hasAnyRole(['student', 'alumni', 'admin', 'registrar', 'cashier'])) {
                $user->assignRole('student');
            }

            static::createStudentProfileIfMissing($user, $lockedEnrollment);
        });

        $enrollment = $payment->fresh()->enrollment;

        NotificationService::send(
            $enrollment->user,
            'GCash Payment Verified',
            "Your GCash payment of ₱{$amount} has been verified. Receipt: {$receiptNo}.",
            'success',
            route('portal.enrollment.payment-info', $enrollment)
        );

        return redirect()->route('cashier.payments.receipt', $enrollment)
            ->with('success', 'GCash payment verified. Student role assigned.');
    }

    // ── reject() ──────────────────────────────────────────────────

    public function reject(Request $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return redirect()->route('cashier.payments.show', $payment->enrollment_id)
                ->with('error', 'This payment has already been processed.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $enrollment = DB::transaction(function () use ($payment, $validated) {
            $lockedEnrollment = Enrollment::whereKey($payment->enrollment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'pending' || $lockedPayment->payment_method !== 'gcash') {
                abort(422, 'This payment is no longer eligible for rejection.');
            }

            $lockedPayment->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['remarks'],
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'processed_by' => Auth::id(),
            ]);

            return $lockedEnrollment;
        });

        NotificationService::send(
            $enrollment->user,
            'GCash Payment Rejected',
            "Your GCash payment proof was rejected. Reason: {$validated['remarks']}. Please resubmit with a valid proof.",
            'danger',
            route('portal.enrollment.payment-info', $enrollment)
        );

        return redirect()->route('cashier.payments.show', $enrollment)
            ->with('success', 'Payment rejected. Applicant has been notified.');
    }

    // ── viewProof() ───────────────────────────────────────────────

    public function viewProof(Payment $payment)
    {
        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($payment->proof_of_payment)
        );
    }

    // ── receipt() ─────────────────────────────────────────────────

    public function receipt(Request $request, Enrollment $enrollment)
    {
        $enrollment->load(['user', 'program']);

        $payment = Payment::where('enrollment_id', $enrollment->id)
            ->where('status', 'verified')
            ->latest()
            ->first();

        if (! $payment) {
            $message = 'No verified payment found for this enrollment.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => $message], 404);
            }

            return redirect()->route('cashier.payments.show', $enrollment)
                ->with('error', $message);
        }

        if ($request->ajax()) {
            return view('cashier.payments._receipt-content', compact('enrollment', 'payment'));
        }

        return view('cashier.payments.receipt', compact('enrollment', 'payment'));
    }

    // ── createStudentProfileIfMissing ─────────────────────────────
    //
    // Called on both walk-in and GCash payment confirmation — the two
    // points at which a new_applicant becomes a student.
    //
    // Sets enrolled_ay from the enrollment's school_year so the student's
    // first AY is permanently anchored in student_profiles without
    // requiring any manual input.
    //
    // This is idempotent — safe to call even if a profile already exists.

    private static function createStudentProfileIfMissing(
        \App\Models\User $user,
        Enrollment $enrollment
    ): void {
        if ($user->studentProfile()->exists()) {
            return;
        }

        $application = Application::where('user_id', $user->id)
            ->where('status', 'approved')
            ->latest()
            ->first();

        $admissionType = $application?->academic_status ?? 'new_student';

        StudentProfile::create([
            'user_id'        => $user->id,
            'student_number' => StudentProfile::generateStudentNumber(),
            'lrn'            => $application?->lrn,
            'admission_type' => $admissionType,
            'gender'         => $application?->gender,
            'birthdate'      => $application?->birthdate,
            'phone'          => $application?->phone,
            'address'        => $application?->current_address ?? $application?->address,
            'program_id'     => $enrollment->program_id,
            // ── New: anchor the AY at the moment of first payment ───────
            'enrolled_ay'    => $enrollment->school_year,
        ]);
    }
}
