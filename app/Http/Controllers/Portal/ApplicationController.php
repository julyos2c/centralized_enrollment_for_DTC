<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
{
    /**
     * Show the authenticated user's own pre-enrollment application.
     *
     * Read-only. The application is derived strictly from the authenticated
     * user's ID (applications.user_id) — there is no route parameter here,
     * so there is no way for one applicant to view another applicant's
     * application by editing a URL.
     */
    public function show()
    {
        $application = Application::where('user_id', Auth::id())
            ->with(['program', 'documents', 'reviewer', 'user.studentProfile', 'user.enrollments.payment'])
            ->latest()
            ->first();

        if (! $application) {
            abort(404, 'No application found for your account.');
        }

        $enrollment = $application->getEnrollmentForProgress();
        $timeline = $application->getProgressTimeline($enrollment);
        $nextStep = $application->getStatusNextStep($enrollment);
        $nextActionUrl = null;
        $nextActionLabel = null;
        if ($application->status === 'approved') {
            if (!$enrollment) {
                $nextStep = 'Select your subjects and submit your official enrollment.';
                $nextActionUrl = route('portal.enrollment.create');
                $nextActionLabel = 'Start enrollment';
            } else {
                $nextActionUrl = route('portal.enrollment.index');
                $nextActionLabel = 'View enrollment';
                $nextStep = str_replace(['Sign in to review', 'Sign in to the student portal to access', 'Sign in to the portal or contact'], ['Review', 'View', 'Contact'], $nextStep);
            }
        }
        foreach ($timeline as &$step) {
            if ($step['key'] === 'review' && $step['status'] === 'completed') {
                $step['description'] = 'The Registrar reviewed your application.';
            }
            if ($step['key'] === 'enrollment' && $application->status === 'approved') {
                $step['description'] = !$enrollment
                    ? 'Enrollment is ready to begin.'
                    : str_replace('Sign in to review', 'Review', $step['description']);
            }
        }
        unset($step);

        return view('portal.application.show', [
            'application' => $application,
            'enrollment' => $enrollment,
            'progressTimeline' => $timeline,
            'nextStep' => $nextStep,
            'nextActionUrl' => $nextActionUrl,
            'nextActionLabel' => $nextActionLabel,
        ]);
    }
}
