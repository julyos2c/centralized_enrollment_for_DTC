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

        return view('portal.application.show', [
            'application' => $application,
            'enrollment' => $enrollment,
            'progressTimeline' => $application->getProgressTimeline($enrollment),
            'nextStep' => $application->getStatusNextStep($enrollment),
        ]);
    }
}
