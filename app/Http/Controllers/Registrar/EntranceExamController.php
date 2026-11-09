<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EntranceExamBooking;
use App\Models\EntranceExamSession;
use App\Models\Setting;
use App\Services\AdmissionNotifier;
use App\Services\BookingException;
use App\Services\EntranceExamBookingService;
use App\Support\AdmissionSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntranceExamController extends Controller
{
    public function __construct(
        private EntranceExamBookingService $bookings,
        private AdmissionNotifier $notifier
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
            'status' => ['nullable', Rule::in(EntranceExamSession::STATUSES)],
        ]);
        $period = $filters['period'] ?? 'upcoming';

        $sessions = EntranceExamSession::query()
            ->withCount([
                'seatedBookings',
                'bookings as pending_count' => fn ($query) => $query->where('status', 'pending'),
                'bookings as attended_count' => fn ($query) => $query->where('status', 'attended'),
                'bookings as absent_count' => fn ($query) => $query->where('status', 'absent'),
            ])
            ->when($period === 'upcoming', fn ($query) => $query->upcoming())
            ->when($period === 'past', fn ($query) => $query->whereDate('exam_date', '<', today()))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('exam_date', $period === 'past' ? 'desc' : 'asc')
            ->orderBy('start_time')
            ->paginate(\App\Support\TablePagination::perPage(10))
            ->withQueryString();

        return view('registrar.admissions.exams.index', [
            'sessions' => $sessions,
            'period' => $period,
            'settings' => AdmissionSettings::all(),
            'pendingBookings' => EntranceExamBooking::where('status', 'pending')->count(),
            'defaultTerm' => [Setting::get('current_school_year'), Setting::get('current_semester')],
        ]);
    }

    public function store(Request $request)
    {
        $session = EntranceExamSession::create($this->validatedSession($request) + [
            'status' => 'open',
            'created_by' => $request->user()->id,
        ]);
        AuditLog::record('admission.exam_session.created', "Created entrance exam session {$session->title} on {$session->scheduleLabel()}.", ['session_id' => $session->id]);

        return redirect()->route('registrar.exams.show', $session)->with('success', 'Exam session created. Applicants can now book it.');
    }

    public function update(Request $request, EntranceExamSession $session)
    {
        $data = $this->validatedSession($request, $session);
        $seated = $session->seatedBookings()->count();
        if ($data['capacity'] < $seated) {
            return back()->withInput()->withErrors(['capacity' => "Capacity cannot be lower than the {$seated} seat(s) already booked."]);
        }

        $scheduleChanged = $session->exam_date->toDateString() !== $data['exam_date']
            || substr($session->start_time, 0, 5) !== $data['start_time']
            || $session->venue !== $data['venue'];
        $session->update($data);
        AuditLog::record('admission.exam_session.updated', "Updated entrance exam session {$session->title}.", ['session_id' => $session->id]);

        if ($scheduleChanged) {
            $session->load('bookings.application.user');
            foreach ($session->bookings->whereIn('status', EntranceExamBooking::CHANGEABLE_STATUSES) as $booking) {
                $this->notifier->applicant(
                    $booking->application,
                    'Entrance exam schedule changed',
                    "Your entrance examination session was updated. New schedule: {$session->scheduleLabel()} at {$session->venue}.",
                    'warning'
                );
            }
        }

        return redirect()->route('registrar.exams.show', $session)->with('success', 'Exam session updated.' . ($scheduleChanged ? ' Booked applicants were notified of the change.' : ''));
    }

    public function setStatus(Request $request, EntranceExamSession $session)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(EntranceExamSession::STATUSES)],
            'reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:500'],
        ]);
        if ($session->status === 'cancelled') {
            return back()->with('error', 'A cancelled session cannot be reopened. Create a new session instead.');
        }

        $session->update(['status' => $data['status']]);
        AuditLog::record('admission.exam_session.' . $data['status'], "Set entrance exam session {$session->title} to {$data['status']}.", ['session_id' => $session->id]);

        $notified = 0;
        if ($data['status'] === 'cancelled') {
            foreach ($session->bookings()->whereIn('status', EntranceExamBooking::CHANGEABLE_STATUSES)->with('application.user')->get() as $booking) {
                if ($this->bookings->cancelByStaff($booking, $data['reason'])) {
                    $notified++;
                    $this->notifier->applicant(
                        $booking->application,
                        'Entrance exam session cancelled',
                        "The examination session on {$session->scheduleLabel()} was cancelled. Reason: {$data['reason']} Please book another available session in your admission portal.",
                        'warning'
                    );
                }
            }
        }

        return back()->with('success', match ($data['status']) {
            'open' => 'Session reopened for booking.',
            'closed' => 'Session closed to new bookings. Existing bookings are kept.',
            'cancelled' => "Session cancelled. {$notified} applicant(s) were notified to rebook.",
        });
    }

    public function show(Request $request, EntranceExamSession $session)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(EntranceExamBooking::STATUS_LABELS))],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $session->loadCount('seatedBookings');
        $bookings = $session->bookings()
            ->with(['application.program', 'rescheduledTo.session'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = '%' . $search . '%';
                $query->whereHas('application', fn ($q) => $q->where('reference_no', 'like', $term)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$term]));
            })
            ->orderByRaw("FIELD(status, 'pending', 'confirmed', 'attended', 'absent', 'rescheduled', 'cancelled', 'rejected')")
            ->orderBy('id')
            ->get();

        $otherSessions = EntranceExamSession::where('status', 'open')->upcoming()
            ->whereKeyNot($session->id)->withCount('seatedBookings')
            ->orderBy('exam_date')->orderBy('start_time')->get()
            ->filter(fn ($candidate) => $candidate->isBookable())->values();

        $statusCounts = $session->bookings()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('registrar.admissions.exams.show', compact('session', 'bookings', 'otherSessions', 'statusCounts'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'exam_mode' => ['required', Rule::in(array_keys(AdmissionSettings::EXAM_MODES))],
            'change_cutoff_hours' => ['required', 'integer', 'min:0', 'max:168'],
        ]);

        Setting::set('admission_exam_mode', $data['exam_mode']);
        Setting::set('admission_exam_booking_requires_approval', $request->boolean('booking_requires_approval') ? '1' : '0');
        Setting::set('admission_exam_requires_documents', $request->boolean('documents_required_before_exam') ? '1' : '0');
        Setting::set('admission_exam_allow_applicant_changes', $request->boolean('applicant_changes_allowed') ? '1' : '0');
        Setting::set('admission_exam_change_cutoff_hours', (string) $data['change_cutoff_hours']);
        AuditLog::record('admission.settings.updated', 'Updated entrance examination settings (mode: ' . $data['exam_mode'] . ').', AdmissionSettings::all());

        return redirect()->route('registrar.exams.index')->with('success', 'Entrance examination settings saved.');
    }

    public function approve(EntranceExamBooking $booking)
    {
        if (!$this->bookings->approve($booking)) {
            return back()->with('warning', 'This booking is no longer awaiting approval.');
        }
        $booking->load('session', 'application.user');
        $this->notifier->applicant($booking->application, 'Entrance exam schedule confirmed',
            "Your entrance examination is confirmed for {$booking->session->scheduleLabel()} at {$booking->session->venue}. Please arrive early and bring a valid ID and your reference number.", 'success');
        $this->audit('approved', $booking);

        return back()->with('success', 'Booking approved and the applicant was notified.');
    }

    public function reject(Request $request, EntranceExamBooking $booking)
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'max:500']]);
        if (!$this->bookings->reject($booking, $data['remarks'])) {
            return back()->with('warning', 'This booking is no longer awaiting approval.');
        }
        $booking->load('session', 'application.user');
        $this->notifier->applicant($booking->application, 'Entrance exam request not approved',
            "Your request for the {$booking->session->scheduleLabel()} session was not approved. Reason: {$data['remarks']} You may book another available session.", 'warning');
        $this->audit('rejected', $booking);

        return back()->with('success', 'Booking declined and the applicant was notified.');
    }

    public function cancel(Request $request, EntranceExamBooking $booking)
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'max:500']]);
        if (!$this->bookings->cancelByStaff($booking, $data['remarks'])) {
            return back()->with('warning', 'Only pending or confirmed bookings can be cancelled.');
        }
        $booking->load('session', 'application.user');
        $this->notifier->applicant($booking->application, 'Entrance exam booking cancelled',
            "Your booking for {$booking->session->scheduleLabel()} was cancelled by the Registrar's Office. Reason: {$data['remarks']} You may book another available session.", 'warning');
        $this->audit('cancelled', $booking);

        return back()->with('success', 'Booking cancelled and the applicant was notified.');
    }

    public function attendance(Request $request, EntranceExamBooking $booking)
    {
        $data = $request->validate(['attendance' => ['required', Rule::in(['attended', 'absent'])]]);
        $booking->load('session');
        if (!$booking->session->hasStarted()) {
            return back()->with('error', 'Attendance can be recorded once the exam session has started.');
        }
        if (!$this->bookings->markAttendance($booking, $request->user(), $data['attendance'])) {
            return back()->with('warning', 'Attendance can only be recorded for confirmed bookings.');
        }
        $this->audit('marked_' . $data['attendance'], $booking);

        if ($data['attendance'] === 'absent') {
            $booking->load('application.user');
            $this->notifier->applicant($booking->application, 'Missed entrance examination',
                "You were marked absent from the {$booking->session->scheduleLabel()} examination. You may book another available session in your admission portal.", 'warning');
        }

        return back()->with('success', 'Attendance recorded.');
    }

    /** Mark every confirmed booking in a started session as attended in one step. */
    public function markAllAttended(Request $request, EntranceExamSession $session)
    {
        if (!$session->hasStarted()) {
            return back()->with('error', 'Attendance can be recorded once the exam session has started.');
        }
        $count = 0;
        foreach ($session->bookings()->where('status', 'confirmed')->get() as $booking) {
            $count += $this->bookings->markAttendance($booking, $request->user(), 'attended') ? 1 : 0;
        }
        AuditLog::record('admission.exam_booking.bulk_attended', "Marked {$count} examinee(s) attended in session {$session->title}.", ['session_id' => $session->id]);

        return back()->with('success', "{$count} examinee(s) marked as attended.");
    }

    public function result(Request $request, EntranceExamBooking $booking)
    {
        $data = $request->validate([
            'result' => ['required', Rule::in(['passed', 'failed'])],
            'score' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'result_remarks' => ['nullable', 'string', 'max:500'],
            'release' => ['nullable', 'boolean'],
        ]);
        if (!$this->bookings->recordResult($booking, $request->user(), $data['result'], isset($data['score']) ? (float) $data['score'] : null, $data['result_remarks'] ?? null)) {
            return back()->with('warning', 'Results can only be recorded for applicants marked as attended.');
        }
        $this->audit('result_recorded', $booking, ['result' => $data['result']]);

        if ($request->boolean('release')) {
            $this->releaseAndNotify($booking);
        }

        return back()->with('success', 'Result saved' . ($request->boolean('release') ? ' and released to the applicant.' : '. It is not visible to the applicant until released.'));
    }

    public function release(EntranceExamBooking $booking)
    {
        if (!$this->releaseAndNotify($booking)) {
            return back()->with('warning', 'There is no unreleased result for this booking.');
        }

        return back()->with('success', 'Result released and the applicant was notified.');
    }

    public function releaseAll(EntranceExamSession $session)
    {
        $count = 0;
        foreach ($session->bookings()->where('status', 'attended')->whereNotNull('result')->whereNull('result_released_at')->get() as $booking) {
            $count += $this->releaseAndNotify($booking) ? 1 : 0;
        }

        return back()->with('success', "{$count} result(s) released.");
    }

    public function reschedule(Request $request, EntranceExamBooking $booking)
    {
        $data = $request->validate(['session_id' => ['required', 'integer', 'exists:entrance_exam_sessions,id']]);
        try {
            $new = $this->bookings->rescheduleByStaff($booking, (int) $data['session_id']);
        } catch (BookingException $exception) {
            return back()->with('error', $exception->getMessage());
        }
        $new->load('session', 'application.user');
        $this->notifier->applicant($new->application, 'Entrance exam rescheduled',
            "The Registrar's Office moved your entrance examination to {$new->session->scheduleLabel()} at {$new->session->venue}.", 'warning');
        $this->audit('rescheduled', $booking, ['new_booking_id' => $new->id]);

        return back()->with('success', 'Applicant moved to ' . $new->session->scheduleLabel() . ' and notified.');
    }

    private function releaseAndNotify(EntranceExamBooking $booking): bool
    {
        if (!$this->bookings->release($booking)) {
            return false;
        }
        $booking->refresh()->load('session', 'application.user');
        $passed = $booking->result === 'passed';
        $this->notifier->applicant(
            $booking->application,
            'Entrance examination result available',
            'Your entrance examination result is now available: ' . ($passed ? 'Passed' : 'Did not pass') . '. '
                . ($passed ? 'The Registrar will now decide on your admission.' : 'Contact the Registrar\'s Office if you have questions.'),
            $passed ? 'success' : 'warning'
        );
        $this->audit('result_released', $booking);

        return true;
    }

    private function audit(string $action, EntranceExamBooking $booking, array $metadata = []): void
    {
        AuditLog::record('admission.exam_booking.' . $action, ucfirst(str_replace('_', ' ', $action)) . " exam booking #{$booking->id} for application #{$booking->application_id}.", $metadata + ['booking_id' => $booking->id]);
    }

    private function validatedSession(Request $request, ?EntranceExamSession $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'exam_date' => ['required', 'date', $existing ? 'after_or_equal:' . min($existing->exam_date->toDateString(), today()->toDateString()) : 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'venue' => ['required', 'string', 'max:200'],
            'capacity' => ['required', 'integer', 'min:1', 'max:2000'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', Rule::in(['1st', '2nd', 'Summer'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $data;
    }
}
