<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EntranceExamBooking;
use App\Models\EntranceExamSession;
use App\Models\User;
use App\Support\AdmissionSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * All seat-changing operations run in a transaction that locks the
 * session row, so the capacity check and the insert cannot interleave
 * with another booking. The unique active_application_id column is the
 * final guard against one applicant holding two seats.
 */
class EntranceExamBookingService
{
    public function __construct(private AdmissionRequirementChecklist $checklist)
    {
    }

    /** Why the applicant cannot book right now, or null when they can. */
    public function bookingBlocker(Application $application): ?string
    {
        if (!AdmissionSettings::examEnabled()) {
            return 'Entrance examination booking is not open.';
        }
        if (!$application->isOpenForAdmissionActivity()) {
            return 'Exam booking is only available while your application is being processed.';
        }
        if ($application->pendingCancellationRequest()->exists()) {
            return 'Exam booking is paused while your cancellation request is pending.';
        }
        if ($application->activeExamBooking()->exists()) {
            return 'You already have an entrance exam booking.';
        }
        if (AdmissionSettings::documentsRequiredBeforeExam()) {
            $summary = $this->checklist->summary($this->checklist->forApplication($application));
            if (!$summary['required_complete']) {
                return 'All required documents must be approved before you can book the entrance exam.';
            }
        }

        return null;
    }

    /** Throws BookingException with an applicant-safe message. */
    public function book(Application $application, int $sessionId, ?User $staff = null): EntranceExamBooking
    {
        $status = $staff || !AdmissionSettings::bookingRequiresApproval() ? 'confirmed' : 'pending';

        return $this->seat($application, $sessionId, $status);
    }

    public function cancelByApplicant(EntranceExamBooking $booking): void
    {
        $this->assertApplicantMayChange($booking);
        $this->transition($booking, EntranceExamBooking::CHANGEABLE_STATUSES, 'cancelled');
    }

    public function rescheduleByApplicant(EntranceExamBooking $booking, int $newSessionId): EntranceExamBooking
    {
        $this->assertApplicantMayChange($booking);

        return $this->move($booking, $newSessionId, AdmissionSettings::bookingRequiresApproval() ? 'pending' : 'confirmed');
    }

    public function rescheduleByStaff(EntranceExamBooking $booking, int $newSessionId): EntranceExamBooking
    {
        return $this->move($booking, $newSessionId, 'confirmed', true);
    }

    public function approve(EntranceExamBooking $booking): bool
    {
        return $this->transition($booking, ['pending'], 'confirmed');
    }

    public function reject(EntranceExamBooking $booking, string $remarks): bool
    {
        return $this->transition($booking, ['pending'], 'rejected', ['staff_remarks' => $remarks]);
    }

    public function cancelByStaff(EntranceExamBooking $booking, string $remarks): bool
    {
        return $this->transition($booking, EntranceExamBooking::CHANGEABLE_STATUSES, 'cancelled', ['staff_remarks' => $remarks]);
    }

    public function markAttendance(EntranceExamBooking $booking, User $staff, string $attendance): bool
    {
        $allowedFrom = $attendance === 'attended' ? ['confirmed', 'absent'] : ['confirmed', 'attended'];
        $extra = [
            'attendance_marked_at' => now(),
            'attendance_marked_by' => $staff->id,
        ];
        if ($attendance === 'absent') {
            // An absent applicant has no result to keep.
            $extra += ['score' => null, 'result' => null, 'result_remarks' => null, 'result_recorded_at' => null, 'result_released_at' => null];
        }

        return $this->transition($booking, $allowedFrom, $attendance, $extra);
    }

    public function recordResult(EntranceExamBooking $booking, User $staff, string $result, ?float $score, ?string $remarks): bool
    {
        return DB::transaction(function () use ($booking, $staff, $result, $score, $remarks) {
            $locked = EntranceExamBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'attended') {
                return false;
            }
            $locked->update([
                'result' => $result,
                'score' => $score,
                'result_remarks' => $remarks,
                'result_recorded_at' => now(),
                'result_recorded_by' => $staff->id,
            ]);
            $booking->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /** Make a recorded result visible to the applicant. */
    public function release(EntranceExamBooking $booking): bool
    {
        return EntranceExamBooking::whereKey($booking->id)
            ->where('status', 'attended')
            ->whereNotNull('result')
            ->whereNull('result_released_at')
            ->update(['result_released_at' => now()]) === 1;
    }

    public function applicantChangeBlocker(EntranceExamBooking $booking): ?string
    {
        if (!AdmissionSettings::applicantChangesAllowed()) {
            return 'Please contact the Registrar\'s Office to change your exam booking.';
        }
        if (!in_array($booking->status, EntranceExamBooking::CHANGEABLE_STATUSES, true)) {
            return 'This booking can no longer be changed.';
        }
        $cutoff = AdmissionSettings::changeCutoffHours();
        if ($booking->session->startsAt()->subHours($cutoff)->isPast()) {
            return "Changes close {$cutoff} hour(s) before the exam. Please contact the Registrar's Office.";
        }

        return null;
    }

    private function assertApplicantMayChange(EntranceExamBooking $booking): void
    {
        $booking->loadMissing('session');
        if ($reason = $this->applicantChangeBlocker($booking)) {
            throw new BookingException($reason);
        }
    }

    private function seat(Application $application, int $sessionId, string $status): EntranceExamBooking
    {
        try {
            return DB::transaction(function () use ($application, $sessionId, $status) {
                $session = EntranceExamSession::whereKey($sessionId)->lockForUpdate()->first();
                $this->assertSessionHasSeat($session);

                return EntranceExamBooking::create([
                    'application_id' => $application->id,
                    'entrance_exam_session_id' => $session->id,
                    'status' => $status,
                    'active_application_id' => EntranceExamBooking::activeKeyFor($status, $application->id),
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw new BookingException('You already have an entrance exam booking.');
            }
            throw $exception;
        }
    }

    private function move(EntranceExamBooking $booking, int $newSessionId, string $status, bool $byStaff = false): EntranceExamBooking
    {
        if ($booking->entrance_exam_session_id === $newSessionId) {
            throw new BookingException('Choose a different exam session.');
        }

        try {
            return $this->applyMove($booking, $newSessionId, $status, $byStaff);
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw new BookingException('This applicant already has another active exam booking.');
            }
            throw $exception;
        }
    }

    private function applyMove(EntranceExamBooking $booking, int $newSessionId, string $status, bool $byStaff): EntranceExamBooking
    {
        return DB::transaction(function () use ($booking, $newSessionId, $status, $byStaff) {
            // Lock sessions in id order to avoid deadlocks between two moves.
            $ids = [$booking->entrance_exam_session_id, $newSessionId];
            sort($ids);
            $sessions = EntranceExamSession::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $locked = EntranceExamBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $movable = $byStaff ? ['pending', 'confirmed', 'absent'] : EntranceExamBooking::CHANGEABLE_STATUSES;
            if (!in_array($locked->status, $movable, true)) {
                throw new BookingException('This booking can no longer be rescheduled.');
            }
            $this->assertSessionHasSeat($sessions->get($newSessionId));

            // Release the old seat and the active key before taking the new one.
            $locked->update(['status' => 'rescheduled', 'active_application_id' => null]);
            $new = EntranceExamBooking::create([
                'application_id' => $locked->application_id,
                'entrance_exam_session_id' => $newSessionId,
                'status' => $status,
                'active_application_id' => EntranceExamBooking::activeKeyFor($status, $locked->application_id),
            ]);
            $locked->update(['rescheduled_to_id' => $new->id]);

            return $new;
        });
    }

    private function assertSessionHasSeat(?EntranceExamSession $session): void
    {
        if (!$session || $session->status !== 'open') {
            throw new BookingException('That exam session is not open for booking.');
        }
        if ($session->hasStarted()) {
            throw new BookingException('That exam session has already started.');
        }
        $taken = $session->bookings()->whereIn('status', EntranceExamBooking::SEATED_STATUSES)->count();
        if ($taken >= $session->capacity) {
            throw new BookingException('That exam session is already full. Please choose another session.');
        }
    }

    private function transition(EntranceExamBooking $booking, array $from, string $to, array $extra = []): bool
    {
        try {
            return $this->applyTransition($booking, $from, $to, $extra);
        } catch (QueryException $exception) {
            // e.g. marking an old absence "attended" after the applicant already rebooked.
            if ($this->isDuplicateKey($exception)) {
                return false;
            }
            throw $exception;
        }
    }

    private function applyTransition(EntranceExamBooking $booking, array $from, string $to, array $extra): bool
    {
        return DB::transaction(function () use ($booking, $from, $to, $extra) {
            $locked = EntranceExamBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status, $from, true)) {
                return false;
            }
            $locked->update(array_merge($extra, [
                'status' => $to,
                'active_application_id' => EntranceExamBooking::activeKeyFor($to, $locked->application_id),
            ]));
            $booking->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062;
    }
}
