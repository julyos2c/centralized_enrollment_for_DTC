<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EnrollmentController extends Controller
{
    // ── create() ──────────────────────────────────────────────────

    public function create()
    {
        $user = Auth::user();
        $fee = Setting::get('enrollment_fee', 500);

        if ($user->hasRole('new_applicant')) {
            // Fetch the single approved application for this user
            $application = $user->applications()
                ->where('status', 'approved')
                ->with('program')
                ->latest()
                ->first();

            if (! $application) {
                return redirect()->route('portal.dashboard')
                    ->with('error', 'You do not have an approved application. Please contact the Registrar.');
            }

            // An earlier term's approved enrollment must not block this term.
            $term = $this->configuredAcademicTerm();
            $existingEnrollment = Enrollment::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->when($term, fn ($query) => $query
                    ->where('school_year', $term['school_year'])
                    ->where('semester', $term['semester']))
                ->first();

            if ($existingEnrollment) {
                return redirect()->route('portal.dashboard')
                    ->with('error', 'You already have an active enrollment.');
            }

            return view('student.enrollment.create', [
                'lockedProgram' => $application->program,
                'application'   => $application,
                'programs'      => null,   // signals the Blade to render locked UI
                'fee'           => $fee,
            ]);
        }

        // student / alumni branch — unchanged
        $programs = Program::all();

        return view('student.enrollment.create', [
            'programs'      => $programs,
            'lockedProgram' => null,
            'application'   => null,
            'fee'           => $fee,
        ]);
    }
    // ── index() — NEW ────────────────────────────────────────────
    public function index()
    {
        $user = Auth::user();

        $enrollments = Enrollment::where('user_id', $user->id)
            ->with(['program', 'payment'])
            ->latest()
            ->paginate(10);

        // Resolve subject names for each enrollment's subject_ids
        $enrollments->each(function ($enrollment) {
            $enrollment->subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
                ->get(['id', 'subject_code', 'subject_name']);
        });

        return view('student.enrollment.index', compact('enrollments'));
    }
    // ── store() ───────────────────────────────────────────────────

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'program_id'  => ['required', 'exists:programs,id'],
            'year_level'  => ['required', 'integer', 'min:1', 'max:5'],
            'semester' => ['required', 'integer', 'in:1,2,3'],
            'is_irregular' => ['nullable', 'boolean'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['exists:course_subjects,id'],
        ]);

        $isIrregular = $request->boolean('is_irregular');
        $term = $this->configuredAcademicTerm();
        if (! $term) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'semester' => 'The current academic year and semester are not configured. Please contact the Registrar.',
            ]);
        }

        // ── Reject duplicate subject submissions outright.
        if (count($validated['subject_ids']) !== count(array_unique($validated['subject_ids']))) {
            abort(422, 'Duplicate subjects were submitted.');
        }

        // ── Authorization: subjects must belong to the selected program.
        // This is the authoritative check — it applies whether the student
        // used the regular (locked year/semester) picker or the irregular
        // (all-subjects, grouped) picker, since the frontend selection is
        // never trusted on its own.
        $validSubjectCount = \App\Models\CourseSubject::where('program_id', $validated['program_id'])
            ->whereIn('id', $validated['subject_ids'])
            ->count();

        if ($validSubjectCount !== count($validated['subject_ids'])) {
            abort(422, 'One or more selected subjects do not belong to the selected program.');
        }

        // ── Authorization: new_applicant must enroll in approved program only
        if ($user->hasRole('new_applicant')) {
            $application = $user->applications()
                ->where('status', 'approved')
                ->latest()
                ->first();

            if (! $application || (int) $application->program_id !== (int) $validated['program_id']) {
                abort(403, 'You may only enroll in your approved program.');
            }
        }

        // ── Guard: no duplicate active enrollments
        $enrollment = DB::transaction(function () use ($user, $validated, $isIrregular, $term) {
            \App\Models\User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existingEnrollment = Enrollment::where('user_id', $user->id)
                ->where('school_year', $term['school_year'])
                ->where('semester', $term['semester'])
                ->whereIn('status', ['pending', 'approved'])
                ->lockForUpdate()
                ->first();

            if ($existingEnrollment) {
                return null;
            }

            return Enrollment::create([
                'user_id'      => $user->id,
                'program_id'   => $validated['program_id'],
                'year_level'   => $validated['year_level'],
                'semester'    => $term['semester'],
                'school_year'  => $term['school_year'],
                'is_irregular' => $isIrregular,
                'subject_ids'  => $validated['subject_ids'],
                'status'       => 'pending',
                'is_paid'      => false,
            ]);
        });

        if (! $enrollment) {
            return redirect()->route('portal.dashboard')
                ->with('error', 'You already have an active enrollment for the current academic term.');
        }

        // Notify all registrars of the new enrollment submission
        $registrars = \App\Models\User::role(['registrar', 'admin'])->get();
        foreach ($registrars as $registrar) {
            \App\Services\NotificationService::send(
                $registrar,
                'New Enrollment Submitted',
                "{$user->name} submitted an enrollment for {$enrollment->program->name} (Year {$enrollment->year_level}) and is awaiting your review.",
                'info',
                route('registrar.enrollments.show', $enrollment)
            );
        }

        return redirect()->route('portal.enrollment.index')->with('success', 'Enrollment submitted successfully. Await Registrar approval.');
    }

    /** Resolve the active term from Registrar-managed academic settings. */
    private function configuredAcademicTerm(): ?array
    {
        $schoolYear = trim((string) Setting::get('current_school_year', ''));
        $semester = [
            '1st' => 1,
            '2nd' => 2,
            'Summer' => 3,
        ][(string) Setting::get('current_semester', '')] ?? null;

        if ($schoolYear === '' || $semester === null) {
            return null;
        }

        return ['school_year' => $schoolYear, 'semester' => $semester];
    }

    // ── submitGcash() — NEW ────────────────────────────────────────

    public function submitGcash(Request $request, Enrollment $enrollment)
    {
        // Ownership check
        if ($enrollment->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:255'],
            'proof_of_payment' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5 MB
            ],
        ]);

        // Lock the enrollment so GCash and walk-in submissions serialize against
        // the same authoritative payment state. A rejected attempt may be retried.
        $proofPath = null;
        try {
            $result = DB::transaction(function () use ($enrollment, $request, $validated, &$proofPath) {
                $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

                if ($lockedEnrollment->status !== 'approved') {
                    return 'not_approved';
                }
                if ($lockedEnrollment->is_paid) {
                    return 'paid';
                }

                $attempts = Payment::where('enrollment_id', $lockedEnrollment->id)
                    ->lockForUpdate()
                    ->get();
                if ($attempts->contains('status', 'verified')) {
                    return 'verified';
                }
                if ($attempts->contains('status', 'pending')) {
                    return 'pending';
                }

                $proofPath = $request->file('proof_of_payment')->store('payment-proofs', 'local');
                Payment::create([
                    'enrollment_id'    => $lockedEnrollment->id,
                    'payment_method'   => 'gcash',
                    'reference_number' => $validated['reference_number'],
                    'proof_of_payment' => $proofPath,
                    'status'           => 'pending',
                    'processed_by'     => null,
                    'verified_by'      => null,
                    'verified_at'      => null,
                    'amount'           => Setting::get('enrollment_fee', 500),
                    'receipt_no'       => null,
                    'paid_at'          => null,
                ]);

                return 'created';
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

        if ($result !== 'created') {
            $message = [
                'not_approved' => 'Your enrollment must be approved before submitting payment.',
                'paid' => 'This enrollment has already been paid.',
                'verified' => 'A verified payment already exists for this enrollment. Contact the Cashier if the records do not match.',
                'pending' => 'You already have a payment pending verification. Please wait for the Cashier to review it.',
            ][$result];

            return back()->with('error', $message);
        }

    foreach (\App\Models\User::role('cashier')->get() as $cashier) {
        \App\Services\NotificationService::send(
            $cashier,
            'New GCash Payment Pending Verification',
            "A GCash payment has been submitted for Enrollment #{$enrollment->id} and is awaiting your review.",
            'info',
            route('cashier.payments.show', $enrollment)
        );
    }

        return back()->with('success', 'GCash payment proof submitted. The Cashier will verify your payment shortly.');
    }

    // ── viewProof() — NEW (student viewing their own proof) ────────

    public function viewProof(Payment $payment)
    {
        if ($payment->enrollment->user_id !== Auth::id()) {
            abort(403);
        }

        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($payment->proof_of_payment)
        );
    }

    // ── showGcashQr() — NEW (shared institutional QR, auth-only) ──

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'GCash QR code not yet configured. Please contact the Registrar.');
        }

        return response()->file(
            Storage::disk('local')->path($path)
        );
    }

    // ── getSubjects() — regular mode unchanged; new grouped "all" mode ──

    public function getSubjects(Request $request, Program $program)
    {
        // Irregular mode: return every subject in this program, grouped by
        // year_level + semester, so the student can pick across groups in
        // a single enrollment. Program scoping still applies — a student
        // can never see or select another program's subjects.
        if ($request->query('mode') === 'all') {
            $subjects = \App\Models\CourseSubject::where('program_id', $program->id)
                ->orderBy('year_level')
                ->orderBy('semester')
                ->orderBy('subject_code')
                ->get(['id', 'subject_code', 'subject_name', 'units', 'year_level', 'semester']);

            $grouped = $subjects->groupBy(function ($s) {
                return $s->year_level . '|' . $s->semester;
            })->map(function ($group, $key) {
                [$year, $sem] = explode('|', $key);
                return [
                    'year_level' => (int) $year,
                    'semester'   => (int) $sem,
                    'subjects'   => $group->values(),
                ];
            })->values();

            return response()->json($grouped);
        }

        // ── Regular mode — unchanged ────────────────────────────────────
        $validated = $request->validate([
            'year_level' => ['required', 'integer', 'min:1', 'max:5'],
            'semester'   => ['required'],
        ]);

        $subjects = \App\Models\CourseSubject::where('program_id', $program->id)
            ->where('year_level', $validated['year_level'])
            ->where('semester', $validated['semester'])
            ->orderBy('subject_code')
            ->get(['id', 'subject_code', 'subject_name']);

        return response()->json($subjects);
    }

    // ── paymentInfo() — unchanged signature, view gains new data ──

    public function paymentInfo(Enrollment $enrollment)
    {
        if ($enrollment->user_id !== Auth::id()) {
            abort(403);
        }

        $enrollment->load('program', 'payment');

        $gcashNumber  = Setting::get('gcash_number');
        $gcashName    = Setting::get('gcash_name');
        $gcashQrReady = Setting::get('gcash_qr_path') && Storage::disk('local')->exists(Setting::get('gcash_qr_path'));
        $fee          = Setting::get('enrollment_fee', 500);

        // Latest payment (could be pending/rejected/verified)
        $latestPayment = Payment::where('enrollment_id', $enrollment->id)
            ->latest()
            ->first();

        return view('student.enrollment.payment-info', compact(
            'enrollment',
            'gcashNumber',
            'gcashName',
            'gcashQrReady',
            'fee',
            'latestPayment'
        ));
    }
}
