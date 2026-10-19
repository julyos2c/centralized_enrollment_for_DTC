<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index()
    {
        $enrollments = Enrollment::with('user', 'program')
            ->where('status', 'pending')
            ->latest()
            ->paginate(10);

        return view('registrar.enrollments.index', compact('enrollments'));
    }

    public function show(Enrollment $enrollment)
    {
        $enrollment->load('user', 'program', 'creditedSubjects.courseSubject');
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids)->get();
        $programSubjects = \App\Models\CourseSubject::where('program_id', $enrollment->program_id)
            ->orderBy('year_level')->orderBy('semester')->orderBy('subject_code')
            ->get();
        return view('registrar.enrollments.show', compact('enrollment', 'subjects', 'programSubjects'));
    }

    /**
     * Registrar-side COR lookup for any enrollment (reached from search or
     * the enrollment show page), not just the ones pending review. Reuses
     * the same student-facing Blade views since the layout is identical —
     * only the enrollment/subjects data and the "back" link differ.
     */
    public function showCor(Enrollment $enrollment)
    {
        if ($enrollment->status !== 'approved' || ! $enrollment->is_paid) {
            return redirect()->route('registrar.enrollments.show', $enrollment)
                ->with('error', 'A Certificate of Registration is only available once enrollment is approved and paid.');
        }

        $enrollment->load('user', 'program');
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
            ->with('schedules')
            ->orderBy('subject_code')
            ->get();
        $totalUnits = $subjects->sum('units');

        return view('student.cor.show', [
            'enrollment' => $enrollment,
            'subjects'   => $subjects,
            'totalUnits' => $totalUnits,
            'backUrl'    => route('registrar.enrollments.show', $enrollment),
        ]);
    }

    public function downloadCor(Enrollment $enrollment)
    {
        if ($enrollment->status !== 'approved' || ! $enrollment->is_paid) {
            return redirect()->route('registrar.enrollments.show', $enrollment)
                ->with('error', 'A Certificate of Registration is only available once enrollment is approved and paid.');
        }

        $enrollment->load('user', 'program');
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
            ->with('schedules')
            ->orderBy('subject_code')
            ->get();
        $totalUnits = $subjects->sum('units');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('student.cor.pdf', compact('enrollment', 'subjects', 'totalUnits'));

        return $pdf->download('COR_' . str_replace(' ', '_', $enrollment->user->name) . '.pdf');
    }

    public function approve(Enrollment $enrollment)
    {
        $enrollment->update(['status' => 'approved']);

        $fee = number_format(\App\Models\Setting::get('enrollment_fee', 500), 2);

        // ── Notify Student ────────────────────────────────────────────
        NotificationService::send(
            $enrollment->user,
            'Enrollment Approved',
            "Your enrollment has been approved by the Registrar. Please proceed to the Cashier to pay the ₱{$fee} enrollment fee.",
            'success',
            route('portal.enrollment.payment-info', $enrollment)
        );

        // ── Notify Cashier ────────────────────────────────────────────
        $cashiers = User::role('cashier')->get();
        foreach ($cashiers as $cashier) {
            NotificationService::send(
                $cashier,
                'Enrollment Ready for Payment',
                $enrollment->user->name . "'s enrollment has been approved. They are ready to pay the ₱{$fee} enrollment fee.",
                'info',
                route('cashier.payments.index')
            );
        }

        // ── Notify Admin ──────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Enrollment Approved',
                'Registrar approved ' . $enrollment->user->name . '\'s enrollment for ' .
                $enrollment->program->name . '.',
                'success',
                route('registrar.enrollments.index')
            );
        }

        return redirect()->route('registrar.enrollments.index')
            ->with('success', 'Enrollment approved. Student and Cashier have been notified.');
    }

    public function reject(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $enrollment->update([
            'status'  => 'rejected',
            'remarks' => $validated['remarks'],
        ]);

        // ── Notify Student ────────────────────────────────────────────
        NotificationService::send(
            $enrollment->user,
            'Enrollment Rejected',
            'Your enrollment was rejected. Reason: ' . $validated['remarks'],
            'danger',
            route('portal.enrollment.index')
        );

        // ── Notify Admin ──────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Enrollment Rejected',
                'Registrar rejected ' . $enrollment->user->name . '\'s enrollment. Reason: ' . $validated['remarks'],
                'warning',
                route('registrar.enrollments.index')
            );
        }

        return redirect()->route('registrar.enrollments.index')
            ->with('success', 'Enrollment rejected. Student has been notified.');
    }

    // ── Alumni Promotion ─────────────────────────────────────────────────────
    //
    // Only available when the enrollment is approved + paid and the user still
    // holds the 'student' role. Admin is allowed as well (route middleware
    // already covers registrar|admin).

    public function promoteToAlumni(Request $request, \App\Models\User $user)
    {
        // The target must currently be a student — guard against double-promotion
        // and against promoting cashiers/registrars/admins via this endpoint.
        if (! $user->hasRole('student')) {
            return redirect()->back()
                ->with('error', 'This action is only available for active students.');
        }

        $validated = $request->validate([
            'graduation_year' => ['required', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
        ]);

        // Update or create the StudentProfile row.
        // updateOrCreate so that existing profile rows (which always exist for
        // paid students) are updated in place rather than duplicated.
        $profile = $user->studentProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['graduation_year' => $validated['graduation_year']]
        );

        // Role swap — atomic, using Spatie's syncRoles to prevent dual-role state.
        $user->syncRoles(['alumni']);

        // Notify the newly promoted alumni.
        NotificationService::send(
            $user,
            'Promoted to Alumni',
            'Congratulations! You have been officially recorded as a DTC graduate (Batch ' .
                $validated['graduation_year'] . '). Your alumni portal is now active.',
            'success',
            route('portal.dashboard')
        );

        return redirect()->route('registrar.enrollments.index')
            ->with('success', $user->name . ' has been promoted to Alumni (Batch ' . $validated['graduation_year'] . ').');
    }

    // ── Credited Subjects (documentation only — see CreditedSubject model) ──
    //
    // Route middleware already restricts this whole controller to
    // registrar|admin, but ownership/consistency of the *data* still needs
    // explicit checks: a course_subject_id from the client is never trusted
    // to actually belong to the enrollment's program.

    public function storeCreditedSubject(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'course_subject_id'  => ['required', 'exists:course_subjects,id'],
            'equivalent_subject' => ['nullable', 'string', 'max:255'],
            'equivalent_school'  => ['nullable', 'string', 'max:255'],
            'credited_units'     => ['nullable', 'numeric', 'min:0', 'max:6'],
            'remarks'            => ['nullable', 'string', 'max:1000'],
        ]);

        // The credited subject must be a real subject in this enrollment's
        // own program — never trust that the client-submitted ID is scoped
        // correctly on its own.
        $belongsToProgram = \App\Models\CourseSubject::where('id', $validated['course_subject_id'])
            ->where('program_id', $enrollment->program_id)
            ->exists();

        if (! $belongsToProgram) {
            abort(422, 'That subject does not belong to this enrollment\'s program.');
        }

        \App\Models\CreditedSubject::updateOrCreate(
            [
                'enrollment_id'      => $enrollment->id,
                'course_subject_id'  => $validated['course_subject_id'],
            ],
            [
                'equivalent_subject' => $validated['equivalent_subject'] ?? null,
                'equivalent_school'  => $validated['equivalent_school'] ?? null,
                'credited_units'     => $validated['credited_units'] ?? null,
                'remarks'            => $validated['remarks'] ?? null,
                'approved_by'        => \Illuminate\Support\Facades\Auth::id(),
                'approved_at'        => now(),
            ]
        );

        return redirect()->route('registrar.enrollments.show', $enrollment)
            ->with('success', 'Credited subject saved.');
    }

    public function updateCreditedSubject(Request $request, \App\Models\CreditedSubject $creditedSubject)
    {
        $validated = $request->validate([
            'equivalent_subject' => ['nullable', 'string', 'max:255'],
            'equivalent_school'  => ['nullable', 'string', 'max:255'],
            'credited_units'     => ['nullable', 'numeric', 'min:0', 'max:6'],
            'remarks'            => ['nullable', 'string', 'max:1000'],
        ]);

        $creditedSubject->update(array_merge($validated, [
            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
            'approved_at' => now(),
        ]));

        return redirect()->route('registrar.enrollments.show', $creditedSubject->enrollment_id)
            ->with('success', 'Credited subject updated.');
    }

    public function destroyCreditedSubject(\App\Models\CreditedSubject $creditedSubject)
    {
        $enrollmentId = $creditedSubject->enrollment_id;
        $creditedSubject->delete();

        return redirect()->route('registrar.enrollments.show', $enrollmentId)
            ->with('success', 'Credited subject removed.');
    }
}