<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    
    public function create()
    {
        // Check for existing active enrollment
        $activeEnrollment = Enrollment::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'approved'])
            ->where('is_paid', false)
            ->first();

        $programs = Program::all();

        return view('student.enrollment.create', compact('programs', 'activeEnrollment'));
    }
    
    public function getSubjects(Program $program, Request $request)
    {
        $subjects = CourseSubject::where('program_id', $program->id)
            ->where('year_level', $request->year_level)
            ->where('semester', $request->semester)
            ->get();

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id'  => ['required', 'exists:programs,id'],
            'year_level'  => ['required', 'integer', 'min:1', 'max:4'],
            'semester'    => ['required', 'integer', 'in:1,2'],
            'subject_ids' => ['required', 'array', 'min:1'],
        ]);

        // ── Prevent duplicate active enrollment ───────────────────────
        $existing = Enrollment::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'approved'])
            ->where('is_paid', false)
            ->first();

        if ($existing) {
            return back()->withErrors([
                'program_id' => 'You already have an active enrollment submission. Please wait for it to be processed before submitting a new one.',
            ]);
        }

        $enrollment = Enrollment::create([
            'user_id'     => Auth::id(),
            'program_id'  => $validated['program_id'],
            'year_level'  => $validated['year_level'],
            'semester'    => $validated['semester'],
            'subject_ids' => $validated['subject_ids'],
            'status'      => 'pending',
        ]);

        $enrollment->load('program');

        // Notify registrars
        $registrars = \App\Models\User::role('registrar')->get();
        foreach ($registrars as $registrar) {
            try {
                \App\Services\NotificationService::send(
                    $registrar,
                    'New Enrollment Submission',
                    Auth::user()->name . ' submitted an enrollment for ' .
                    ($enrollment->program->name ?? 'a program') .
                    ' — Year ' . $validated['year_level'] .
                    ', Sem ' . $validated['semester'] . '.',
                    'info',
                    route('registrar.enrollments.index')
                );
            } catch (\Exception $e) {
                \Log::error('Registrar notification failed: ' . $e->getMessage());
            }
        }

        // Notify admins
        $admins = \App\Models\User::role('admin')->get();
        foreach ($admins as $admin) {
            try {
                \App\Services\NotificationService::send(
                    $admin,
                    'New Enrollment Submission',
                    Auth::user()->name . ' submitted an enrollment for ' .
                    ($enrollment->program->name ?? 'a program') . '.',
                    'info',
                    route('registrar.enrollments.index')
                );
            } catch (\Exception $e) {
                \Log::error('Admin notification failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('portal.dashboard')
            ->with('success', 'Enrollment submitted! Please wait for Registrar approval.');
    }

    public function index()
    {
        $enrollments = Enrollment::where('user_id', Auth::id())->latest()->get();
        return view('student.enrollment.index', compact('enrollments'));
    }

    public function paymentInfo(Enrollment $enrollment)
    {
        abort_if($enrollment->user_id !== auth()->id(), 403);

        return view('student.enrollment.payment-info', compact('enrollment'));
    }
    
}
