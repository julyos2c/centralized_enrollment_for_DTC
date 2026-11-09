<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\AdmissionUpdate;
use App\Models\AdmissionRequirement;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\EntranceExamBooking;
use App\Models\EntranceExamSession;
use App\Services\AdmissionDocumentService;
use App\Services\AdmissionNotifier;
use App\Services\AdmissionProgress;
use App\Services\AdmissionRequirementChecklist;
use App\Services\BookingException;
use App\Services\EntranceExamBookingService;
use App\Support\AdmissionSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Self-service admission portal for applicants who do not have an account
 * yet. Access is granted to the browser session either right after the
 * application is submitted or through a time-limited signed link emailed
 * to the address on the application. Approved applicants who signed in
 * reach their own application the same way.
 */
class AdmissionPortalController extends Controller
{
    public const SESSION_KEY = 'admission_portal';
    private const LINK_MINUTES = 60 * 24;
    private const SESSION_HOURS = 12;

    public static function grantAccess(Request $request, Application $application): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'application_id' => $application->id,
            'expires_at' => now()->addHours(self::SESSION_HOURS)->getTimestamp(),
        ]);
    }

    /** Email a sign-in link. The response never reveals whether details matched. */
    public function requestLink(Request $request)
    {
        $validated = $request->validate([
            'reference_no' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $application = Application::where('reference_no', trim($validated['reference_no']))
            ->where('email', trim($validated['email']))
            ->first();

        if ($application) {
            $url = URL::temporarySignedRoute('public.admission.enter', now()->addMinutes(self::LINK_MINUTES), ['application' => $application->id]);
            try {
                Mail::to($application->email)->send(new AdmissionUpdate(
                    trim($application->first_name) ?: 'Applicant',
                    'Your admission portal link',
                    'Use this secure link to upload your requirements, book your entrance examination, and track application ' . $application->reference_no . '. The link expires in 24 hours. If you did not ask for it, you can ignore this email.',
                    $url,
                    'Open my admission portal'
                ));
            } catch (\Throwable $exception) {
                Log::warning('Admission portal link email could not be sent.', [
                    'application_id' => $application->id,
                    'exception' => get_class($exception),
                ]);
            }
        }

        return redirect()->route('public.application.status.form')
            ->with('success', 'If the details match an application, a secure link has been sent to that email address. It expires in 24 hours.');
    }

    public function enter(Request $request, Application $application)
    {
        $request->session()->regenerate();
        self::grantAccess($request, $application);

        // Redirect so the signed token does not stay in the address bar or history.
        return redirect()->route('public.admission.portal');
    }

    public function leave(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('public.application.status.form')->with('success', 'You have left the admission portal.');
    }

    public function show(Request $request, AdmissionProgress $progress)
    {
        $application = $this->application($request);
        $application->load(['program', 'pendingCancellationRequest']);
        $tracker = $progress->build($application);

        $sessions = collect();
        if ($tracker['examEnabled']) {
            $sessions = EntranceExamSession::where('status', 'open')
                ->upcoming()
                ->withCount('seatedBookings')
                ->orderBy('exam_date')->orderBy('start_time')
                ->limit(60)
                ->get()
                ->filter(fn (EntranceExamSession $session) => $session->isBookable())
                ->values();
        }

        return view('public.admission.portal', [
            'application' => $application,
            'tracker' => $tracker,
            'sessions' => $sessions,
            'settings' => AdmissionSettings::all(),
            'changeBlocker' => $tracker['booking'] && in_array($tracker['booking']->status, EntranceExamBooking::CHANGEABLE_STATUSES, true)
                ? app(EntranceExamBookingService::class)->applicantChangeBlocker($tracker['booking'])
                : null,
            'signedInApplicant' => $request->user() !== null,
        ]);
    }

    public function upload(Request $request, AdmissionRequirement $requirement, AdmissionDocumentService $documents, AdmissionNotifier $notifier, AdmissionRequirementChecklist $checklist)
    {
        $application = $this->application($request);

        if (in_array($application->status, ['rejected', 'cancelled'], true)) {
            return back()->with('error', 'Documents can no longer be uploaded for this application.');
        }
        $item = $checklist->forApplication($application)->first(fn ($row) => $row['requirement']->id === $requirement->id);
        if (!$item) {
            abort(404);
        }
        if ($item['state'] === 'approved') {
            return back()->with('error', 'This document was already approved and cannot be replaced. Contact the Registrar\'s Office if it needs correction.');
        }

        $request->validate(
            ['file' => $requirement->uploadRules(true)],
            [
                'file.mimes' => 'Upload a ' . strtoupper(implode(', ', $requirement->extensions())) . ' file.',
                'file.max' => 'The file is larger than the allowed size for this requirement.',
            ]
        );

        $wasRejected = $item['state'] === 'rejected';
        $resumedReview = false;
        try {
            $document = $documents->store($application, $requirement, $request->file('file'));
            // Uploading after a revision request returns the application to review.
            $resumedReview = DB::transaction(function () use ($application) {
                $locked = Application::whereKey($application->id)->lockForUpdate()->first();
                if ($locked->status !== 'revision_required') {
                    return false;
                }
                $locked->update(['status' => 'under_review']);
                return true;
            });
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'We could not save your file. Please try again.');
        }

        $name = $application->full_name;
        $notifier->staff(
            $application,
            $wasRejected ? 'Applicant Resubmitted a Document' : 'Applicant Uploaded a Document',
            "{$name} ({$application->reference_no}) uploaded {$requirement->name} for review." . ($resumedReview ? ' The application returned to review after a revision request.' : ''),
            'info'
        );

        return redirect()->to(route('public.admission.portal') . '#requirements')
            ->with('success', $requirement->name . ' was uploaded and is now awaiting review.');
    }

    public function document(Request $request, ApplicationDocument $document)
    {
        $application = $this->application($request);
        abort_unless($document->application_id === $application->id, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);
        $mime = $disk->mimeType($document->file_path);
        $inline = in_array($mime, ApplicationDocument::PREVIEWABLE_MIME_TYPES, true);

        return $disk->response($document->file_path, $document->original_name, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function book(Request $request, EntranceExamBookingService $service, AdmissionNotifier $notifier)
    {
        $application = $this->application($request);
        $validated = $request->validate(['session_id' => ['required', 'integer']]);

        if ($blocker = $service->bookingBlocker($application)) {
            return back()->with('error', $blocker);
        }

        try {
            $booking = $service->book($application, (int) $validated['session_id']);
        } catch (BookingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->notifyBooking($application, $booking->load('session'), $notifier, 'booked');

        return redirect()->to(route('public.admission.portal') . '#exam')
            ->with('success', $booking->status === 'confirmed'
                ? 'Your entrance exam is booked for ' . $booking->session->scheduleLabel() . '.'
                : 'Your exam schedule request was sent and is awaiting approval.');
    }

    public function reschedule(Request $request, EntranceExamBooking $booking, EntranceExamBookingService $service, AdmissionNotifier $notifier)
    {
        $application = $this->application($request);
        abort_unless($booking->application_id === $application->id, 404);
        $validated = $request->validate(['session_id' => ['required', 'integer']]);

        try {
            $new = $service->rescheduleByApplicant($booking, (int) $validated['session_id']);
        } catch (BookingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->notifyBooking($application, $new->load('session'), $notifier, 'rescheduled');

        return redirect()->to(route('public.admission.portal') . '#exam')
            ->with('success', 'Your exam was moved to ' . $new->session->scheduleLabel() . '.');
    }

    public function cancel(Request $request, EntranceExamBooking $booking, EntranceExamBookingService $service, AdmissionNotifier $notifier)
    {
        $application = $this->application($request);
        abort_unless($booking->application_id === $application->id, 404);

        try {
            $service->cancelByApplicant($booking);
        } catch (BookingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $booking->load('session');
        $notifier->staff(
            $application,
            'Entrance Exam Booking Cancelled',
            "{$application->full_name} ({$application->reference_no}) cancelled the exam booking for {$booking->session->scheduleLabel()}.",
            'warning',
            route('registrar.exams.show', $booking->session)
        );

        return redirect()->to(route('public.admission.portal') . '#exam')->with('success', 'Your exam booking was cancelled. You can book another session.');
    }

    private function notifyBooking(Application $application, EntranceExamBooking $booking, AdmissionNotifier $notifier, string $action): void
    {
        $session = $booking->session;
        $when = $session->scheduleLabel() . ' at ' . $session->venue;

        $notifier->applicant(
            $application,
            $booking->status === 'confirmed' ? 'Entrance exam schedule confirmed' : 'Entrance exam request received',
            $booking->status === 'confirmed'
                ? "Your entrance examination is scheduled for {$when}. Please arrive early and bring a valid ID and your reference number."
                : "We received your request for the {$when} examination session. The Registrar will confirm it and you will receive another email.",
            $booking->status === 'confirmed' ? 'success' : 'info'
        );
        $notifier->staff(
            $application,
            $action === 'rescheduled' ? 'Entrance Exam Rescheduled' : 'Entrance Exam Booked',
            "{$application->full_name} ({$application->reference_no}) {$action} the exam session on {$when}" . ($booking->status === 'pending' ? ' and is awaiting approval.' : '.'),
            'info',
            route('registrar.exams.show', $session)
        );
    }

    private function application(Request $request): Application
    {
        $access = $request->session()->get(self::SESSION_KEY);
        if (is_array($access) && ($access['expires_at'] ?? 0) > now()->getTimestamp()) {
            $application = Application::find($access['application_id'] ?? null);
            if ($application) {
                return $application;
            }
        }

        // Signed-in applicants can always reach their own application.
        if ($user = $request->user()) {
            $application = Application::where('user_id', $user->id)->latest('id')->first();
            if ($application) {
                return $application;
            }
        }

        abort(redirect()->route('public.application.status.form')
            ->with('error', 'Your admission portal session has ended. Look up your application to get a new secure link.'));
    }
}
