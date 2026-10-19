<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationApproved;
use App\Mail\ApplicationRejected;
use App\Mail\ApplicationRevisionRequired;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Program;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in([
                'new_student', 'transferee', 'shiftee', 'returnee', 'cross_enrollee',
            ])],
            'status' => ['nullable', Rule::in([
                'submitted', 'under_review', 'revision_required', 'approved', 'rejected',
            ])],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
        ]);

        $applications = Application::with(['program', 'user', 'reviewer', 'documents'])
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $term = '%' . $search . '%';
                $query->where(function ($q) use ($term) {
                    $q->whereRaw("CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) LIKE ?", [$term])
                      ->orWhere('email', 'like', $term)
                      ->orWhere('reference_no', 'like', $term);
                });
            })
            ->when($filters['type'] ?? null, function ($query, $type) {
                $query->where('academic_status', $type);
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($filters['program_id'] ?? null, function ($query, $programId) {
                $query->where('program_id', $programId);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $programs = Program::orderBy('name')->get(['id', 'name']);

        return view('registrar.applications.index', compact('applications', 'programs'));
    }

    public function show(Application $application)
    {
        $application->load(['program', 'user', 'reviewer', 'documents']);

        return view('registrar.applications.show', compact('application'));
    }

    public function startReview(Application $application)
    {
        // ── Only allow starting review for submitted applications ───────
        if ($application->status !== 'submitted') {
            return redirect()->route('registrar.applications.show', $application)
                ->with('error', 'This application has already been reviewed.');
        }

        $application->update([
            'status' => 'under_review',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // ── Notify Student if they have an account ─────────────────────
        if ($application->user) {
            NotificationService::send(
                $application->user,
                'Application Under Review',
                'Your application is now being reviewed by the Registrar.',
                'info',
                route('portal.application.show')
            );
        }

        return redirect()->route('registrar.applications.show', $application)
            ->with('success', 'Review started. The applicant has been notified.');
    }

    public function approve(Application $application)
    {
        // ── Only allow approval for applications under review ───────────
        if ($application->status !== 'under_review') {
            return redirect()->route('registrar.applications.show', $application)
                ->with('error', 'This application must be under review before it can be approved.');
        }

        // ── Prevent duplicate approval ──────────────────────────────────
        if ($application->user_id !== null || $application->status === 'approved') {
            return redirect()->route('registrar.applications.index')
                ->with('error', 'This application has already been approved.');
        }

        // ── Prevent duplicate User by email ─────────────────────────────
        if (User::where('email', $application->email)->exists()) {
            return redirect()->route('registrar.applications.index')
                ->with('error', 'An account with this email address already exists. This application cannot be auto-approved and needs manual review.');
        }

        try {
            $approval = DB::transaction(function () use ($application) {
                $lockedApplication = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();

                if ($lockedApplication->status !== 'under_review' || $lockedApplication->user_id !== null) {
                    return null;
                }

                $name = trim(
                    $lockedApplication->first_name . ' ' .
                    ($lockedApplication->middle_name ? $lockedApplication->middle_name . ' ' : '') .
                    $lockedApplication->last_name
                );

                $user = User::create([
                    'name'     => $name,
                    'email'    => $lockedApplication->email,
                    'status'   => 'active',
                    // Unusable random placeholder — the applicant will set their
                    // own password via the Step 3A password-setup/activation flow.
                    'password' => Hash::make(Str::random(40)),
                ]);

                $user->assignRole('new_applicant');

                $lockedApplication->update([
                    'status'      => 'approved',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'user_id'     => $user->id,
                ]);

                $token = Password::broker('users')->createToken($user);

                $setupUrl = route('password.setup.show', [
                    'token' => $token,
                    'email' => $lockedApplication->email,
                ]);

                return [$user, $setupUrl, $lockedApplication];
            });
        } catch (\Throwable $e) {
            return redirect()->route('registrar.applications.index')
                ->with('error', 'Approval failed and no changes were saved. Please try again.');
        }

        if ($approval === null) {
            return redirect()->route('registrar.applications.show', $application)
                ->with('error', 'This application is no longer awaiting approval.');
        }

        [$user, $setupUrl, $application] = $approval;

        // Send the email only after the transaction has committed.
        try {
            Mail::to($application->email)->send(
                new ApplicationApproved(
                    $application,
                    $setupUrl,
                    config('auth.passwords.users.expire', 60)
                )
            );
        } catch (\Throwable $notificationException) {
            report($notificationException);
        }

        $applicantName = trim($application->first_name . ' ' . $application->last_name);

        try {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                try {
                    NotificationService::send(
                        $admin,
                        'Application Approved',
                        'Registrar approved the pre-enrollment application for ' . $applicantName . '.',
                        'success',
                        route('registrar.applications.show', $application)
                    );
                } catch (\Throwable $notificationException) {
                    Log::warning('Admin application approval notification failed.', [
                        'application_id' => $application->id,
                        'recipient_user_id' => $admin->id,
                    ]);
                }
            }
        } catch (\Throwable $notificationException) {
            Log::warning('Admin application approval notifications could not be loaded.', [
                'application_id' => $application->id,
            ]);
        }

        try {
            NotificationService::send(
                $user,
                'Application Approved',
                'Congratulations! Your pre-enrollment application has been approved. You can now proceed with enrollment.',
                'success',
                route('portal.application.show')
            );
        } catch (\Throwable $notificationException) {
            Log::warning('Applicant approval notification failed.', [
                'application_id' => $application->id,
                'recipient_user_id' => $user->id,
            ]);
        }

        return redirect()->route('registrar.applications.index')
            ->with('success', 'Application approved. The applicant can request a new activation link if the email does not arrive.');
    }

    public function reject(Request $request, Application $application)
    {
        // ── Only allow rejection for applications under review ───────────
        if ($application->status !== 'under_review') {
            return redirect()->route('registrar.applications.show', $application)
                ->with('error', 'This application must be under review before it can be rejected.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $application->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'remarks'     => $validated['remarks'],
        ]);

        Mail::to($application->email)->send(
            new ApplicationRejected($application)
        );

        // ── Notify Admin ─────────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Application Rejected',
                'Registrar rejected the pre-enrollment application for ' . $application->first_name . ' ' . $application->last_name . '. Reason: ' . $validated['remarks'],
                'warning',
                route('registrar.applications.show', $application)
            );
        }

        // ── Notify Student if they have an account ─────────────────────
        if ($application->user) {
            NotificationService::send(
                $application->user,
                'Application Rejected',
                'Your pre-enrollment application was not approved. Reason: ' . $validated['remarks'],
                'danger',
                route('portal.application.show')
            );
        }

        return redirect()->route('registrar.applications.index')
            ->with('success', 'Application rejected. The applicant has been notified by email.');
    }

    public function revision(Request $request, Application $application)
    {
        // ── Only allow revision requests for applications under review ───
        if ($application->status !== 'under_review') {
            return redirect()->route('registrar.applications.show', $application)
                ->with('error', 'This application must be under review before requesting revision.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $application->update([
            'status'      => 'revision_required',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'remarks'     => $validated['remarks'],
        ]);

        Mail::to($application->email)->send(
            new ApplicationRevisionRequired($application)
        );

        // ── Notify Admin ─────────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Application Requires Revision',
                'Registrar requested revision on the pre-enrollment application for ' . $application->first_name . ' ' . $application->last_name . '.',
                'info',
                route('registrar.applications.show', $application)
            );
        }

        // ── Notify Student if they have an account ─────────────────────
        if ($application->user) {
            NotificationService::send(
                $application->user,
                'Action Required: Application Revision',
                'Your application requires document corrections. Please review the remarks and contact the Registrar.',
                'warning',
                route('portal.application.show')
            );
        }

        return redirect()->route('registrar.applications.index')
            ->with('success', 'Revision requested. The applicant has been notified by email.');
    }

    public function downloadDocument(Application $application, ApplicationDocument $document)
    {
        if ($document->application_id !== $application->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'The requested document could not be found.');
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_name
        );
    }
}
