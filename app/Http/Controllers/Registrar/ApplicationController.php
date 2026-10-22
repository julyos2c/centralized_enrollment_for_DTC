<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationApproved;
use App\Mail\ApplicationRejected;
use App\Mail\ApplicationRevisionRequired;
use App\Models\Application;
use App\Models\ApplicationCancellationRequest;
use App\Models\ApplicationDocument;
use App\Models\Program;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ApplicationCancellationService;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
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
                'submitted', 'under_review', 'revision_required', 'approved', 'rejected', 'cancelled',
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
        $application->load(['program', 'user', 'reviewer', 'documents', 'cancellationRequests.requester', 'cancellationRequests.reviewer']);

        return view('registrar.applications.show', compact('application'));
    }

    public function startReview(Application $application)
    {
        // ── Only allow starting review for submitted applications ───────
        $updated = DB::transaction(function () use ($application) {
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'submitted' || $locked->pendingCancellationRequest()->exists()) {
                return false;
            }
            $locked->update(['status' => 'under_review', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            return true;
        });

        if (! $updated) {
            return redirect()->route('registrar.applications.show', $application)
                ->with('error', 'This application cannot start review while a cancellation request is pending or after another status change.');
        }
        $application->refresh();

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

                if ($lockedApplication->status !== 'under_review' || $lockedApplication->user_id !== null || $lockedApplication->pendingCancellationRequest()->exists()) {
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
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $application = $this->transitionApplication($application, 'rejected', $validated['remarks']);
        if (! $application) {
            return redirect()->route('registrar.applications.show', $request->route('application'))
                ->with('error', 'This application must be under review before it can be rejected.');
        }

        $delivery = $this->notifyApplicationDecision(
            $application,
            new ApplicationRejected($application),
            'Application Rejected',
            'Registrar rejected the pre-enrollment application for ' . $application->first_name . ' ' . $application->last_name . '. Reason: ' . $validated['remarks'],
            'warning',
            'Application Rejected',
            'Your pre-enrollment application was not approved. Reason: ' . $validated['remarks'],
            'danger'
        );

        return redirect()->route('registrar.applications.index')
            ->with($delivery['ok'] ? 'success' : 'warning', 'Application rejected. ' . $delivery['message']);
    }

    public function revision(Request $request, Application $application)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $application = $this->transitionApplication($application, 'revision_required', $validated['remarks']);
        if (! $application) {
            return redirect()->route('registrar.applications.show', $request->route('application'))
                ->with('error', 'This application must be under review before requesting revision.');
        }

        $delivery = $this->notifyApplicationDecision(
            $application,
            new ApplicationRevisionRequired($application),
            'Application Requires Revision',
            'Registrar requested revision on the pre-enrollment application for ' . $application->first_name . ' ' . $application->last_name . '.',
            'info',
            'Action Required: Application Revision',
            'Your application requires document corrections. Please review the remarks and contact the Registrar.',
            'warning'
        );

        return redirect()->route('registrar.applications.index')
            ->with($delivery['ok'] ? 'success' : 'warning', 'Revision requested. ' . $delivery['message']);
    }

    public function approveCancellation(Request $request, ApplicationCancellationRequest $cancellationRequest, ApplicationCancellationService $service)
    {
        abort_unless($request->user()->hasAnyRole(['registrar', 'admin']), 403);
        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);
        $result = $service->resolve($cancellationRequest->id, $request->user(), true, $validated['remarks'] ?? null);
        $application = $result['application'];
        if ($result['changed']) {
            $this->notifyCancellationApplicant($application, 'Application Cancellation Approved', 'The Registrar approved your cancellation request. The application is now cancelled.');
        }

        return redirect()->route('registrar.applications.show', $application)
            ->with($result['changed'] ? 'success' : 'warning', $result['changed'] ? 'Cancellation approved. The application record and history were retained.' : 'This cancellation request was already resolved.');
    }

    public function rejectCancellation(Request $request, ApplicationCancellationRequest $cancellationRequest, ApplicationCancellationService $service)
    {
        abort_unless($request->user()->hasAnyRole(['registrar', 'admin']), 403);
        $validated = $request->validate(['remarks' => ['required', 'string', 'max:500']]);
        $result = $service->resolve($cancellationRequest->id, $request->user(), false, $validated['remarks']);
        $application = $result['application'];
        if ($result['changed']) {
            $this->notifyCancellationApplicant($application, 'Application Cancellation Request Declined', 'The Registrar did not approve your cancellation request. Your application remains '.$application->status.'.');
        }

        return redirect()->route('registrar.applications.show', $application)
            ->with($result['changed'] ? 'success' : 'warning', $result['changed'] ? 'Cancellation request declined. The application remains in its previous workflow.' : 'This cancellation request was already resolved.');
    }

    private function notifyCancellationApplicant(Application $application, string $subject, string $message): void
    {
        if (! filter_var($application->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            Mail::raw($message."\n\nReference: ".$application->reference_no, function ($mail) use ($application, $subject) {
                $mail->to($application->email)->subject($subject);
            });
        } catch (\Throwable $exception) {
            Log::warning('Cancellation decision email delivery failed.', [
                'application_id' => $application->id,
                'exception' => get_class($exception),
            ]);
        }
    }

    /** Atomically claim the review transition so retried requests cannot duplicate notices. */
    private function transitionApplication(Application $application, string $status, string $remarks): ?Application
    {
        return DB::transaction(function () use ($application, $status, $remarks) {
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'under_review' || $locked->pendingCancellationRequest()->exists()) {
                return null;
            }

            $locked->update([
                'status' => $status,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'remarks' => $remarks,
            ]);

            return $locked->fresh(['user']);
        });
    }

    /** Deliver email and in-app alerts after the state transition has committed. */
    private function notifyApplicationDecision(
        Application $application,
        Mailable $mail,
        string $adminTitle,
        string $adminMessage,
        string $adminTone,
        string $applicantTitle,
        string $applicantMessage,
        string $applicantTone
    ): array {
        $emailSent = false;
        if (filter_var($application->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($application->email)->send($mail);
                $emailSent = true;
            } catch (\Throwable $exception) {
                Log::warning('Application decision email delivery failed.', [
                    'application_id' => $application->id,
                    'exception' => get_class($exception),
                ]);
            }
        } else {
            Log::warning('Application decision email has no valid destination.', [
                'application_id' => $application->id,
            ]);
        }

        $notificationsOk = true;
        try {
            foreach (User::role('admin')->get() as $admin) {
                $notificationsOk = NotificationService::sendSafely(
                    $admin,
                    $adminTitle,
                    $adminMessage,
                    $adminTone,
                    route('registrar.applications.show', $application)
                ) && $notificationsOk;
            }
        } catch (\Throwable $exception) {
            $notificationsOk = false;
            Log::warning('Application decision admin notifications failed.', [
                'application_id' => $application->id,
                'exception' => get_class($exception),
            ]);
        }

        if ($application->user) {
            $notificationsOk = NotificationService::sendSafely(
                $application->user,
                $applicantTitle,
                $applicantMessage,
                $applicantTone,
                route('portal.application.show')
            ) && $notificationsOk;
        }

        $messages = [];
        if (! $emailSent) {
            $messages[] = 'Email could not be delivered; ' . ($application->email ? 'the saved email address may need correction or SMTP needs attention.' : 'no email address is on file.');
        }
        if (! $notificationsOk) {
            $messages[] = 'One or more in-app notifications could not be saved.';
        }

        return [
            'ok' => $emailSent && $notificationsOk,
            'message' => $messages ? implode(' ', $messages) : 'The applicant has been notified by email and in the portal where an account exists.',
        ];
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
