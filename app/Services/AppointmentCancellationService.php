<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentCancellationService
{
    /**
     * Cancel an appointment while keeping the separate document and payment
     * lifecycles consistent. Applicant-side cancellation is blocked once a
     * linked document is already in progress, ready, or released.
     */
    public function cancel(Appointment $appointment, User $actor, Request $request, ?string $remarks = null): array
    {
        $isStaff = $actor->hasAnyRole(['registrar', 'admin']);
        abort_if(! $isStaff && $appointment->user_id !== $actor->id, 403);

        $result = DB::transaction(function () use ($appointment, $actor, $request, $remarks, $isStaff) {
            $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ($lockedAppointment->status === 'cancelled') {
                return ['result' => 'already_cancelled'];
            }

            $allowedStatuses = $isStaff ? ['pending', 'confirmed'] : ['pending'];
            if (! in_array($lockedAppointment->status, $allowedStatuses, true)) {
                return ['result' => 'invalid_status'];
            }

            $legacyPayments = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                ->lockForUpdate()
                ->get();
            if ($legacyPayments->contains('status', 'verified')) {
                return ['result' => 'verified_legacy_payment'];
            }

            $documentRequests = DocumentRequest::where('appointment_id', $lockedAppointment->id)
                ->lockForUpdate()
                ->get();

            if (! $isStaff && $documentRequests->contains(fn ($documentRequest) => in_array($documentRequest->status, ['processing', 'ready', 'released'], true))) {
                return ['result' => 'resolved_request'];
            }

            $requestDisposition = 'none';

            foreach ($documentRequests as $documentRequest) {
                if ($documentRequest->status === 'submitted') {
                    $documentRequest->update(['status' => 'cancelled']);
                    $requestDisposition = 'cancelled';

                    AuditLog::create([
                        'user_id' => $actor->id,
                        'action' => 'document_request.cancelled_with_appointment',
                        'description' => "Cancelled submitted document request #{$documentRequest->id} with appointment #{$lockedAppointment->id}.",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'metadata' => [
                            'document_request_id' => $documentRequest->id,
                            'appointment_id' => $lockedAppointment->id,
                            'previous_request_status' => 'submitted',
                            'payment_status' => $documentRequest->payment_status,
                            'is_paid' => $documentRequest->is_paid,
                            'verified_payment_preserved' => $documentRequest->is_paid || $documentRequest->payment_status === 'verified',
                        ],
                    ]);
                } elseif ($documentRequest->status !== 'cancelled') {
                // Processing, ready, and released requests remain active and
                // linked to the cancelled appointment for history and follow-up.
                    $requestDisposition = 'preserved';
                }
            }

            $lockedAppointment->update([
                'status' => 'cancelled',
                'remarks' => $remarks ?? $lockedAppointment->remarks,
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'appointment.cancelled',
                'description' => "Cancelled appointment #{$lockedAppointment->id}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'appointment_id' => $lockedAppointment->id,
                    'document_request_ids' => $documentRequests->pluck('id')->all(),
                    'request_disposition' => $requestDisposition,
                    'request_statuses' => $documentRequests->mapWithKeys(fn ($item) => [$item->id => $item->fresh()->status])->all(),
                    'remarks' => $remarks,
                ],
            ]);

            return [
                'result' => 'cancelled',
                'appointment' => $lockedAppointment->fresh(['user', 'slot']),
                'document_requests' => $documentRequests->map(fn ($item) => $item->fresh()),
                'document_request' => $documentRequests->first()?->fresh(),
                'request_disposition' => $requestDisposition,
            ];
        });

        if ($result['result'] !== 'cancelled') {
            return $result;
        }

        $cancelledAppointment = $result['appointment'];
        $documentRequests = $result['document_requests'];
        $documentRequest = $result['document_request'];
        $requestDisposition = $result['request_disposition'];
        $result['user_message'] = $this->applicantMessage($documentRequests, $requestDisposition, $remarks);
        $destination = $cancelledAppointment->user->hasRole('alumni')
            ? route('portal.documents.index')
            : route('portal.appointments.index');
        if (!$cancelledAppointment->user->hasAnyRole(['student', 'alumni'])) $destination = route('portal.dashboard');

        if ($isStaff) {
            $applicantMessage = $this->applicantMessage($documentRequests, $requestDisposition, $remarks);
            NotificationService::sendUserUpdateSafely(
                $cancelledAppointment->user,
                'appointment-cancelled:'.$cancelledAppointment->id,
                'Appointment Cancelled',
                $applicantMessage,
                'warning',
                $destination,
                'View appointment details'
            );
        } else {
            $staffMessage = $this->staffMessage($cancelledAppointment, $documentRequest, $requestDisposition);
            foreach (User::role(['registrar', 'admin'])->get() as $staff) {
                NotificationService::sendSafely(
                    $staff,
                    'Appointment Cancelled',
                    $staffMessage,
                    'warning',
                    route('registrar.appointments.index')
                );
            }
        }

        if ($documentRequest && $documentRequests->contains(fn ($item) => !$item->paymentNotRequired())) {
            $cashierMessage = $this->cashierMessage($cancelledAppointment, $documentRequest, $requestDisposition);
            foreach (User::role('cashier')->get() as $cashier) {
                NotificationService::sendSafely(
                    $cashier,
                    'Appointment Cancelled for Document Request',
                    $cashierMessage,
                    'warning',
                    route('cashier.document-payments.show', $documentRequest)
                );
            }
        }

        return $result;
    }

    private function applicantMessage($documentRequests, string $disposition, ?string $remarks): string
    {
        $reason = $remarks ? ' Reason: ' . $remarks : '';
        if ($documentRequests->isEmpty()) {
            return 'Your appointment was cancelled.' . $reason;
        }
        if ($documentRequests->count() > 1) {
            if ($disposition === 'cancelled') {
                return 'Your appointment and unstarted document requests were cancelled. Any payment proof and payment records were preserved; contact the Cashier and Registrar to resolve payment before submitting again.' . $reason;
            }
            return 'Your appointment was cancelled, but the linked document requests remain active with their existing processing statuses. Contact the Registrar to arrange another pickup time.' . $reason;
        }
        $documentRequest = $documentRequests->first();
        if ($disposition === 'cancelled') {
            if ($documentRequest->is_paid || $documentRequest->payment_status === 'verified') {
                return 'Your appointment and unstarted document request were cancelled. The verified payment and receipt were preserved; contact the Cashier and Registrar to resolve the paid request before submitting another.' . $reason;
            }

            if ($documentRequest->payment_status === 'pending_verification') {
                return 'Your appointment and unstarted document request were cancelled. Your payment proof was preserved. Contact the Cashier before making another payment.' . $reason;
            }

            return 'Your appointment and unstarted document request were cancelled. You can submit a new request when ready.' . $reason;
        }

        return 'Your appointment was cancelled, but your document request remains ' . $documentRequest->status . '. Contact the Registrar to arrange the next pickup step.' . $reason;
    }

    private function staffMessage(Appointment $appointment, ?DocumentRequest $documentRequest, string $disposition): string
    {
        $message = $appointment->user->name . ' cancelled an appointment for ' . ucfirst($appointment->document_type) . '.';
        if (! $documentRequest) {
            return $message;
        }

        return $message . ' Document request #' . $documentRequest->id . ' is '
            . ($disposition === 'cancelled' ? 'cancelled' : 'still ' . $documentRequest->status)
            . '; payment status: ' . str_replace('_', ' ', $documentRequest->payment_status) . '.';
    }

    private function cashierMessage(Appointment $appointment, DocumentRequest $documentRequest, string $disposition): string
    {
        $message = 'Appointment #' . $appointment->id . ' for ' . $appointment->user->name . ' was cancelled. Document request #'
            . $documentRequest->id . ' is ' . ($disposition === 'cancelled' ? 'cancelled' : 'still ' . $documentRequest->status)
            . '; payment status: ' . str_replace('_', ' ', $documentRequest->payment_status) . '.';

        if ($documentRequest->is_paid || $documentRequest->payment_status === 'verified') {
            $message .= ' The verified payment and receipt were preserved; manual reconciliation may be needed.';
        }

        return $message;
    }
}
