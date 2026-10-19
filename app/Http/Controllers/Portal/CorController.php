<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class CorController extends Controller
{
    /**
     * Resolve the student's current officially-enrolled record and its
     * subjects (with schedule) as a ready-to-render collection.
     *
     * Mirrors the subject_ids resolution already used in
     * Student\EnrollmentController@index, but scoped to the enrollment
     * that is actually paid — a COR only makes sense once enrollment
     * is final, not while still pending/awaiting payment.
     *
     * Prefers the enrollment matching the currently configured school
     * year (snapshotted on the row at creation time), falling back to
     * the most recent approved+paid row for older records created
     * before the school_year column existed.
     */
    private function resolveActiveEnrollment()
    {
        $user = Auth::user();
        $currentSchoolYear = \App\Models\Setting::get('current_school_year');

        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('is_paid', true)
            ->where('school_year', $currentSchoolYear)
            ->with('program', 'user')
            ->latest()
            ->first();

        if (! $enrollment) {
            // Fall back for legacy rows with no school_year recorded.
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('status', 'approved')
                ->where('is_paid', true)
                ->with('program', 'user')
                ->latest()
                ->first();
        }

        if (! $enrollment) {
            return null;
        }

        $subjects = CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
            ->with('schedules')
            ->orderBy('subject_code')
            ->get();

        return [$enrollment, $subjects];
    }

    public function show()
    {
        $resolved = $this->resolveActiveEnrollment();

        if (! $resolved) {
            return redirect()->route('portal.dashboard')
                ->with('error', 'Your Certificate of Registration will be available once your enrollment is approved and paid.');
        }

        [$enrollment, $subjects] = $resolved;
        $totalUnits = $subjects->sum('units');

        return view('student.cor.show', compact('enrollment', 'subjects', 'totalUnits'));
    }

    public function download()
    {
        $resolved = $this->resolveActiveEnrollment();

        if (! $resolved) {
            return redirect()->route('portal.dashboard')
                ->with('error', 'Your Certificate of Registration will be available once your enrollment is approved and paid.');
        }

        [$enrollment, $subjects] = $resolved;
        $totalUnits = $subjects->sum('units');

        $pdf = Pdf::loadView('student.cor.pdf', compact('enrollment', 'subjects', 'totalUnits'));

        return $pdf->download('COR_' . str_replace(' ', '_', $enrollment->user->name) . '.pdf');
    }
}