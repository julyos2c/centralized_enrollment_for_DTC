<?php

namespace App\Services;

use App\Models\Application;
use App\Support\AdmissionSettings;

/**
 * Applicant-facing admission tracker: one ordered list of steps plus the
 * single most useful next action. Step statuses reuse the vocabulary of
 * Application::getProgressTimeline() (completed, current, action_required,
 * pending, skipped, rejected) so both trackers render the same way.
 */
class AdmissionProgress
{
    public function __construct(
        private AdmissionRequirementChecklist $checklist,
        private EntranceExamBookingService $bookings
    ) {
    }

    public function build(Application $application): array
    {
        $application->loadMissing(['program', 'activeExamBooking.session', 'examBookings.session', 'user.studentProfile', 'user.enrollments.payment']);
        $checklist = $this->checklist->forApplication($application);
        $summary = $this->checklist->summary($checklist);
        $examEnabled = AdmissionSettings::examEnabled();
        $booking = $application->activeExamBooking;
        $latestBooking = $application->examBookings->sortByDesc('id')->first();
        $closed = in_array($application->status, ['rejected', 'cancelled'], true);
        $decided = in_array($application->status, ['approved', 'rejected', 'cancelled'], true);

        $steps = [];
        $steps[] = $this->step('submitted', 'Application submitted', 'Reference ' . $application->reference_no . ' was received.', 'completed');

        $docsStatus = match (true) {
            $summary['rejected'] > 0 && !$closed => 'action_required',
            $summary['required_missing'] > 0 && !$closed => 'action_required',
            $summary['uploaded'] > 0 || $summary['total'] === 0 => 'completed',
            $closed => 'skipped',
            default => 'current',
        };
        $steps[] = $this->step('requirements', 'Upload requirements', $this->requirementsDescription($summary), $docsStatus);

        $reviewStatus = match (true) {
            $summary['rejected'] > 0 => 'action_required',
            $summary['total'] === 0 || ($summary['required_complete'] && $summary['awaiting_review'] === 0 && $summary['uploaded'] > 0) => 'completed',
            $summary['required_complete'] && $summary['required'] > 0 => 'completed',
            $closed => 'skipped',
            $summary['awaiting_review'] > 0 => 'current',
            default => 'pending',
        };
        $steps[] = $this->step('review', 'Document review', $this->reviewDescription($summary), $reviewStatus);

        if ($examEnabled || $latestBooking) {
            $steps[] = $this->examStep($application, $booking, $latestBooking, $closed, $decided);
        }

        $steps[] = match ($application->status) {
            'approved' => $this->step('decision', 'Admission decision', 'You have been admitted. Proceed to enrollment.', 'completed', $application->reviewed_at),
            'rejected' => $this->step('decision', 'Admission decision', 'Your application was not approved.' . ($application->remarks ? ' Remarks: ' . $application->remarks : ''), 'rejected', $application->reviewed_at),
            'cancelled' => $this->step('decision', 'Admission decision', 'This application was cancelled.', 'skipped'),
            'revision_required' => $this->step('decision', 'Admission decision', 'The Registrar requested corrections before deciding.', 'action_required'),
            default => $this->step('decision', 'Admission decision', 'The Registrar decides after reviewing your requirements' . ($examEnabled ? ' and exam result.' : '.'), 'pending'),
        };

        $enrollment = $application->getEnrollmentForProgress();
        $timeline = $application->getProgressTimeline($enrollment);
        $enrollmentStep = collect($timeline)->firstWhere('key', 'enrollment');
        $steps[] = $this->step('enrollment', $enrollmentStep['label'], $enrollmentStep['description'], $enrollmentStep['status'], $enrollmentStep['timestamp']);

        return [
            'steps' => $steps,
            'checklist' => $checklist,
            'summary' => $summary,
            'booking' => $booking,
            'latestBooking' => $latestBooking,
            'examEnabled' => $examEnabled,
            'bookingBlocker' => $examEnabled ? $this->bookings->bookingBlocker($application) : null,
            'nextStep' => $this->nextStep($application, $summary, $examEnabled, $booking, $latestBooking, $enrollment),
            'percent' => $this->percent($steps),
        ];
    }

    private function examStep(Application $application, $booking, $latestBooking, bool $closed, bool $decided): array
    {
        if ($booking && $booking->status === 'attended') {
            if ($booking->isResultReleased()) {
                $passed = $booking->result === 'passed';
                return $this->step('exam', 'Entrance examination', 'Result: ' . ($passed ? 'Passed' : 'Did not pass') . ($booking->score !== null ? ' (score ' . rtrim(rtrim((string) $booking->score, '0'), '.') . ')' : '') . '.', $passed ? 'completed' : 'rejected', $booking->result_released_at);
            }
            return $this->step('exam', 'Entrance examination', 'You attended the exam. Results will appear here once released.', 'current', $booking->attendance_marked_at);
        }
        if ($booking && $booking->status === 'confirmed') {
            return $this->step('exam', 'Entrance examination', 'Scheduled: ' . $booking->session->scheduleLabel() . ' at ' . $booking->session->venue . '.', 'current');
        }
        if ($booking && $booking->status === 'pending') {
            return $this->step('exam', 'Entrance examination', 'Your requested schedule (' . $booking->session->scheduleLabel() . ') is awaiting approval.', 'current');
        }
        if ($closed || ($decided && !$booking)) {
            return $this->step('exam', 'Entrance examination', 'No exam is needed for this application now.', 'skipped');
        }
        if ($latestBooking && in_array($latestBooking->status, ['absent', 'rejected', 'cancelled'], true)) {
            $reason = match ($latestBooking->status) {
                'absent' => 'You were marked absent from your last exam session.',
                'rejected' => 'Your last booking was not approved' . ($latestBooking->staff_remarks ? ': ' . $latestBooking->staff_remarks : '') . '.',
                default => 'Your last booking was cancelled.',
            };
            return $this->step('exam', 'Entrance examination', $reason . ' Book another available session.', 'action_required');
        }

        return $this->step('exam', 'Entrance examination', 'Book an available examination session.', 'pending');
    }

    private function nextStep(Application $application, array $summary, bool $examEnabled, $booking, $latestBooking, $enrollment): array
    {
        if ($application->status === 'cancelled') {
            return ['title' => 'Application cancelled', 'description' => 'This application can no longer proceed. Contact the Registrar\'s Office to apply again.', 'anchor' => null];
        }
        if ($application->status === 'rejected') {
            return ['title' => 'Application not approved', 'description' => 'Review the Registrar remarks. Contact the Registrar\'s Office with your reference number for guidance.', 'anchor' => null];
        }
        if ($application->status === 'approved') {
            return ['title' => 'Proceed to enrollment', 'description' => $application->getStatusNextStep($enrollment), 'anchor' => null];
        }
        if ($summary['rejected'] > 0) {
            return ['title' => 'Replace rejected documents', 'description' => 'Upload a corrected copy of: ' . implode(', ', $summary['rejected_names']) . '. The rejection reason is shown on each document.', 'anchor' => '#requirements'];
        }
        if ($summary['required_missing'] > 0) {
            return ['title' => 'Upload required documents', 'description' => 'Still needed: ' . implode(', ', $summary['missing_names']) . '.', 'anchor' => '#requirements'];
        }
        if ($application->status === 'revision_required') {
            return ['title' => 'Address the requested corrections', 'description' => 'Read the Registrar remarks and upload corrected documents. Your application returns to review when you upload.', 'anchor' => '#requirements'];
        }
        if ($examEnabled) {
            if ($booking?->status === 'confirmed') {
                return ['title' => 'Attend your entrance exam', 'description' => $booking->session->scheduleLabel() . ' at ' . $booking->session->venue . '. Arrive early and bring a valid ID and your reference number.', 'anchor' => '#exam'];
            }
            if ($booking?->status === 'pending') {
                return ['title' => 'Wait for exam schedule approval', 'description' => 'The Registrar will confirm your requested schedule. You will receive an email.', 'anchor' => '#exam'];
            }
            if ($booking?->status === 'attended' && $booking->isResultReleased() && $booking->result === 'failed') {
                return ['title' => 'Exam result released', 'description' => 'Your result is shown below. The Registrar will inform you of the admission decision; contact the Registrar\'s Office if you have questions.', 'anchor' => '#exam'];
            }
            if ($booking?->status === 'attended' && !$booking->isResultReleased()) {
                return ['title' => 'Wait for your exam result', 'description' => 'Results are released by the Registrar\'s Office. You will receive an email.', 'anchor' => '#exam'];
            }
            if (!$booking) {
                $blocker = $this->bookings->bookingBlocker($application);
                if ($blocker === null) {
                    return ['title' => 'Book your entrance exam', 'description' => 'Choose an available examination date and time below.', 'anchor' => '#exam'];
                }
                if ($summary['awaiting_review'] > 0) {
                    return ['title' => 'Wait for document review', 'description' => 'The Registrar is reviewing your documents. Exam booking opens once your required documents are approved.', 'anchor' => '#requirements'];
                }
            }
        }
        if ($summary['awaiting_review'] > 0) {
            return ['title' => 'Wait for document review', 'description' => 'The Registrar is reviewing your uploaded documents. You will be emailed about the result.', 'anchor' => '#requirements'];
        }

        return ['title' => 'Wait for the admission decision', 'description' => 'No action is needed right now. You will be emailed when the Registrar decides on your application.', 'anchor' => null];
    }

    private function requirementsDescription(array $summary): string
    {
        if ($summary['total'] === 0) {
            return 'No document uploads are required for your applicant type at this stage.';
        }
        $text = $summary['uploaded'] . ' of ' . $summary['total'] . ' document(s) uploaded';
        if ($summary['required'] > 0) {
            $text .= ' · ' . $summary['required_approved'] . ' of ' . $summary['required'] . ' required approved';
        }
        if ($summary['rejected'] > 0) {
            $text .= ' · ' . $summary['rejected'] . ' need replacement';
        }

        return $text . '.';
    }

    private function reviewDescription(array $summary): string
    {
        if ($summary['total'] === 0) {
            return 'Nothing to review.';
        }

        return $summary['approved'] . ' approved, ' . $summary['awaiting_review'] . ' awaiting review, ' . $summary['rejected'] . ' rejected.';
    }

    private function percent(array $steps): int
    {
        $countable = collect($steps)->reject(fn ($step) => $step['status'] === 'skipped');
        if ($countable->isEmpty()) {
            return 0;
        }

        return (int) round($countable->where('status', 'completed')->count() / $countable->count() * 100);
    }

    private function step(string $key, string $label, string $description, string $status, $timestamp = null): array
    {
        return compact('key', 'label', 'description', 'status', 'timestamp');
    }
}
